<?hh

class AcceptInviteController extends BaseController {
  public static function getPath(): string {
    return '/invite/accept';
  }

  public static function getConfig(): ControllerConfig {
    return (new ControllerConfig())->setUserState(array(UserState::Accepted));
  }

  public static function get(): :xhp {
    $user = Session::getUser();
    if ($user->getRoles()->contains(UserRole::Confirmed) ||
        $user->getRoles()->contains(UserRole::Denied)) {
      Flash::set(Flash::ERROR, "You've already responded to your invite");
      Route::redirect(DashboardController::getPath());
    }
    return <nucleus:accept-invite user={Session::getUser()} />;
  }

  public static function post(): void {
    $user = Session::getUser();

    // The user has denied their invite
    if (isset($_POST['deny'])) {
      Roles::insert(UserRole::Denied, $user->getID());
      Flash::set(
        Flash::SUCCESS,
        "Your invitation was successfully declined.",
      );
      Route::redirect(DashboardController::getPath());
    }

    // An accept wasn't sent, error
    if (!isset($_POST['accept'])) {
      http_response_code(400);
      Flash::set(Flash::SUCCESS, "Something went wrong, please try again");
      Route::redirect(self::getPath());
    }

    // Make sure the Code of Conduct is accepted
    if (!isset($_POST['coc'])) {
      Flash::set(
        Flash::ERROR,
        "The MLH Code of Conduct must be accepted before you can confirm your invitation",
      );
      Route::redirect(self::getPath());
    }

    // The user is uploading a resume
    if (isset($_FILES['resume'])) {
      // Make sure the resume is a PDF
      $file_type = pathinfo(
        basename($_FILES["resume"]["name"]),
        PATHINFO_EXTENSION,
      );
      if ($file_type != "pdf") {
        http_response_code(400);
        Flash::set(Flash::ERROR, "Résumé made be in pdf format");
        Route::redirect(self::getPath());
      }

      $upload_dir = "uploads/".$user->getID();

      // Create the upload directory for the user
      if (!file_exists($upload_dir)) {
        mkdir($upload_dir);
      }

      // Move the file to its final home
      if (!move_uploaded_file(
            $_FILES['resume']['tmp_name'],
            $upload_dir."/resume.pdf",
          )) {
        Flash::set(Flash::ERROR, "Résumé was not uploaded successfully");
        Route::redirect(self::getPath());
      }
    }

    // Get the demographic information
    $data = Map {
      'gender' => $user->getGender(),
      'school' => $user->getSchool(),
      'major' => $user->getMajor(),
    };

    if (isset($_POST['first-hackathon'])) {
      $data['is_first_hackathon'] =
        $_POST['first-hackathon'] === "yes" ? true : false;
    }

    if (isset($_POST['year'])) {
      if($_POST['year'] !== "Select one") {
        $data['year'] = $_POST['year'];
      }
    }

    $race = null;
    if (isset($_POST['race'])) {
      $races = Vector {};
      foreach ($_POST['race'] as $race) {
        if ($race === "other") {
          $races[] = $_POST['otherrace'];
          continue;
        }
        $races[] = $race;
      }
    }
    $data['race'] = $race;

    // Send demographic information to keen
    $client = KeenIO\Client\KeenIOClient::factory(
      [
        'projectId' => Config::get('Keen')['project_id'],
        'writeKey' => Config::get('Keen')['write_key'],
        'readKey' => Config::get('Keen')['read_key'],
      ],
    );
    $client->addEvent('confirmation', $data->toArray());

    // Set the user to confirmed
    Roles::insert(UserRole::Confirmed, $user->getID());
    Flash::set(Flash::SUCCESS, "You've successfully confirmed.");
    Route::redirect(DashboardController::getPath());
  }
}