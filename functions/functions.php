<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require './vendor/autoload.php';

/**********************************  **********************************/

function clean($string){

    return htmlentities($string);
}

function redirect($location){

    return header("Location: {$location}");
}

function set_message($msg){

    if(!empty($msg)){
        $_SESSION['message'] = $msg;
    } else {
        $msg = "";
    }
}

function display_message(){
    
    if(isset($_SESSION['message'])) {
        echo $_SESSION['message'];
        unset($_SESSION['message']);
    }
}

function validation_errors($error_msg){
    $msg = <<<DELIMITER
            <div class="alert alert-danger alert-dimissible" role="alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong>Warning!</strong> $error_msg
            </div>
        DELIMITER;
    return $msg;
}

function token_generator(){
    
    $token = $_SESSION['token'] = md5(uniqid(mt_rand(), true));
    return $token;
}

function send_email($email, $subject, $msg, $headers){

    // return mail($email, $subject, $msg, $headers);
    $mail = new PHPMailer(true);
    try{
        $mail->setLanguage('pl', './vendor/phpmailer/phpmailer/language');
        $mail->isSMTP();
        $mail->Host       = Config::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = Config::SMTP_USER;
        $mail->Password   = Config::SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = Config::SMTP_PORT;
        $mail->isHTML(true);
        $mail->CharSet = 'utf-8';

        $email->setFrom($headers);
        $email->addAddress($email);

        $mail->Subject    = $subject;
        $mail->Body       = $msg;
        $mail->AltBody    = $msg;

        $mail->send();
    } catch (Exception $e){
        echo validation_errors("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
    return true;
}

/********************************** Validation **********************************/

function email_exists($email){
    $sql = "SELECT id FROM users WHERE email = '$email'";
    $result = query($sql);
    if(row_count($result) == 1){
        return true;
    } else {
        return false;
    }
}

function username_exists($username){
    $sql = "SELECT id FROM users WHERE username = '$username'";
    $result = query($sql);
    if(row_count($result) == 1){
        return true;
    } else {
        return false;
    }
}

function compare_strings($string, $to_compare, $sign, $display, &$errors){
    if ($sign == '<'){
        if(strlen($string) < $to_compare){
            $errors[] = "Your {$display} cannot be less than {$to_compare} characters.";
        }
    } elseif ($sign == '>'){
        if(strlen($string) > $to_compare){
            $errors[] = "Your {$display} cannot be more than {$to_compare} characters.";
        }
    }
}

function validate_user_registration(){

    $errors = [];
    $min_characters = 3;
    $max_characters = 20;

    if($_SERVER['REQUEST_METHOD'] == "POST"){

        $first_name         = clean($_POST['first_name']);
        $last_name          = clean($_POST['last_name']);
        $username           = clean($_POST['username']);
        $email              = clean($_POST['email']);
        $password           = clean($_POST['password']);
        $confirm_password   = clean($_POST['confirm_password']);
        

        compare_strings($first_name, $min_characters, '<', 'First name', $errors);
        compare_strings($last_name, $min_characters, '<', 'Last name', $errors);
        compare_strings($username, $min_characters, '<', 'Username', $errors);
        compare_strings($first_name, $max_characters, '>', 'First name', $errors);
        compare_strings($last_name, $max_characters, '>', 'Last name', $errors);
        compare_strings($username, $max_characters, '>', 'Username', $errors);

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $errors[] = "Invalid email format";
        }

        if(email_exists($email)){
            $errors[] = "Email is already registered.";
        }

        if(username_exists($username)){
            $errors[] = "Username is already taken.";
        }
        
        if ($password !== $confirm_password){
            $errors[] = "Your password fields do not match";
        }

        if(!empty($errors)){
            foreach ($errors as $error){

                echo validation_errors($error);
            }
        } else {
            if(register_user($first_name, $last_name, $username, $email, $password)){

                set_message("<p class='bg-succes text-center'>Please check your email or SPAM folder for an activation mail</p>");
                redirect("index.php");

                echo "user registered";
            }
        }
    } // POST
} // function end



/********************************** User register **********************************/



function register_user($first_name, $last_name, $username, $email, $password){

    $first_name     = escape($first_name);
    $last_name      = escape($last_name);
    $username       = escape($username);
    $email          = escape($email);
    $password       = escape($password);

    if(email_exists($email)){
        return false;
    } elseif (username_exists($username)) {
        return false;
    } else {
        $password        = password_hash($password, PASSWORD_BCRYPT, array('cost'=>12)); //md5($password);
        $validation_code = md5($username . microtime());

        $sql = "INSERT INTO users(first_name, last_name, username, email, password, validation_code, active)";
        $sql.= " VALUES('$first_name', '$last_name', '$username', '$email', '$password', '$validation_code', 0)";
        $result = query($sql);
        confirm($result);
        
        $subject = "Activate account";
        $msg = " Please click the link below to activate your Account

        <a href=\"".Config::DEV_URL."/activate.php?email={$email}&code={$validation_code}\">Reset Password</a>
        ";

        $headers = "From: noreplay@mywebsite.com";

        send_email($email, $subject, $msg, $headers);

        return true;
    }
    return false;
}



/********************************** Activate user **********************************/


function activate_user(){

    if($_SERVER['REQUEST_METHOD'] == "GET"){
        if(isset($_GET['email'])){
            $email = clean($_GET['email']);
            $validation_code = clean($_GET['code']);

            $sql = "SELECT id FROM users WHERE email = '".escape($_GET['email'])."' AND validation_code = '".escape($_GET['code'])."' ";
            $result = query($sql);
            confirm($result);

            if (row_count($result) == 1){
                $sql_active = "UPDATE users SET active = 1, validation_code = 0 WHERE email='".escape($email)."' AND validation_code = '".escape($validation_code)."' ";
                $result_active = query($sql_active);
                confirm($result_active);
                set_message("<p class=bg-success'>Your account has been activated, please login</p>");
                redirect("login.php");
            } else {
                set_message("<p class=bg-danger'>Sorry, your could not be activated</p>");
                redirect("login.php");
            }
        }
    }


}



/********************************** Validate user login **********************************/



function validate_user_login(){

    $errors = [];


    if($_SERVER['REQUEST_METHOD'] == "POST"){

        $email         = clean($_POST['email']);
        $password      = clean($_POST['password']);
        $remember      = isset($_POST['remember']);

        if(empty($email)){
            $errors[] = "Email field cannot be empty.";
        } 
        if(empty($email)){
            $errors[] = "Password field cannot be empty.";
        }

        if(!empty($errors)){
            foreach ($errors as $error){

                echo validation_errors($error);
            }
        } else {
            if(login_user($email, $password, $remember)){
                redirect("admin.php");
            } else {
                echo validation_errors("Something went wrong :(");
            }
        }
    }
}



/********************************** User login functions **********************************/



function login_user($email, $password, $remember){

    $sql    = "SELECT password, id FROM users WHERE email = '".escape($email)."' AND active = 1";
    $result = query($sql);
    confirm($result);

    if(row_count($result) == 1){

        $row            = fetch_array($result);
        $db_password    = $row['password'];

        if(password_verify($password, $db_password)){
            if($remember == "on"){

                setcookie('email', $email, time() + 60 * 60 * 24 * 2);
            }

            $_SESSION['email'] = $email;

            return true;
        }
    } 
    return false;
}


function logged_in(){

    if(isset($_SESSION['email']) || isset($_COOKIE['email'])){
        return true;
    }
    return false;
}



/********************************** Recover password functions **********************************/



function recover_password(){

    if($_SERVER['REQUEST_METHOD'] == 'POST'){

        if(isset($_SESSION['token']) && $_POST['token'] === $_SESSION['token']){
            
            $email = clean($_POST['email']);
            
            if(email_exists($email)){
                $validation_code = md5($email . microtime());

                setcookie('temporary_access_code', $validation_code, time() + 60*5);

                $sql = "UPDATE users SET validation_code = '".escape($validation_code)."' WHERE email = '".escape($email)."' ";
                $result = query($sql);
                confirm($result);

                $subject    = "Password reset";
                $msg        = "Here is your password reset code {$validation_code}. Code is active for five minutes only.
                                
                            Click <a href=\"".Config::DEV_URL."/code.php?email={$email}&code={$validation_code}\">here</a> to reset your password 
                            
                            ";
                $headers    = "From: noreplay@mywebsite.com";

                
                send_email($email, $subject, $msg, $headers);
                set_message("<p class='bg-succes text-center'>Please check your mailbox for a password reset link</p>");
                redirect("index.php");

            } else {
                echo validation_errors("This email is not exist");
            } // email
        } else {
            redirect("index.php");
        } //token

        if(isset($_POST['cancel-submit'])){
            redirect("login.php");
        }


    }
}


function validate_code(){

    if(isset($_COOKIE['temporary_access_code'])){

        if(!isset($_GET['email']) && !isset($_GET['code'])){
            redirect("index.php");
        } elseif(empty($_GET['email'] || empty($_GET['code']))){
            redirect("index.php");
        } else {
            if(isset($_POST['code'])){
                $email              = clean($_GET['email']);
                $validation_code    = clean($_POST['code']);
                $sql                = "SELECT id FROM users WHERE validation_code = '".escape($validation_code)."' AND email = '".escape($email)."' ";
                $result             = query($sql);
                
                confirm($result);

                if(row_count($result) == 1){
                    setcookie('temporary_access_code', $validation_code, time() + 60*3);
                    redirect("reset.php?email=$email&code=$validation_code");
                } else {
                    echo validation_errors("Sorry, wrong validation code.");
                }
            }
        }
    } else {
        set_message("<p class='bg-danger text-center'>Code expired</p>");
        redirect("recover.php");
    }
}



/********************************** Password reset functions **********************************/



function password_reset(){

    if(isset($_COOKIE['temp_access_code'])){
        if(isset($_GET['email']) && isset($_GET['code'])){
            if(isset($_SESSION['token']) && isset($_POST['token']) && $_POST['token'] === $_SESSION['token']){
                if($_POST['password'] == $_POST['confirm_password']){
                    $update_password = md5($_POST['password']);

                    $sql = "UPDATE users SET password = '".escape($update_password)."', validation_code = 0, active = 1 WHERE email = '".escape($_POST['email'])."' ";
                    $result = query($sql);
                    confirm($result);
                    
                    set_message("<p class='bg-success text-center'>Password has been updated. Please log in.</p>");
                    redirect("login.php");
                }
            }
        }
    } else {
        set_message("<p class='bg-danger text-center'>Sorry, your time has expired</p>");
        redirect("recover.php");
    }
}

?>