<?php
namespace Stanford\REDCaptcha;

include_once("Util.php");

use Survey;

class REDCaptcha extends \ExternalModules\AbstractExternalModule
{

    function hook_survey_page_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1 ){
        // Skip recaptcha if a record is already defined
        if (!empty($record)) return false;


        // IS THIS A PUBLIC SURVEY?
        self::log("POST", $_POST);
        self::log("Record", $record);
        // Get the survey id
        global $Proj;
        $survey_id = @$Proj->forms[$instrument]['survey_id'];
        self::log("SurveyID: " . $survey_id);

        // Get the public hash for this arm
        $public_hash = Survey::getSurveyHash($survey_id, $event_id);
        self::log("Hash: " . $public_hash);

        // Skip if not the public survey
        if ($public_hash !== $survey_hash) {
            // This is not the public survey!
            return false;
        }


        self::log("This is the public survey");


        // Is this a recaptcha post-back
        if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['g-recaptcha-response'])) {
            $captcha=$_POST['g-recaptcha-response'];
            $secretKey =  $this->getSystemSetting("secret-key");
            $ip = $_SERVER['REMOTE_ADDR'];
            $response = http_get("https://www.google.com/recaptcha/api/siteverify?secret=".$secretKey."&response=".$captcha."&remoteip=".$ip);
            $responseKeys = json_decode($response,true);
            self::log($responseKeys);

            if ($responseKeys['success'] !== true) {
                self::log("recaptcha failed");
                $recaptcha_error_message = $this->getProjectSetting("recaptcha-error-message");
                if (empty($recaptcha_error_message)) $recaptcha_error_message = "Invalid reCaptcha Response";
                echo "<div class='invalid-response alert alert-danger text-center'>$recaptcha_error_message</div>";
            } else {
                // Success
                self::log("recaptcha success");
                // Let survey proceed
                return true;
            }
        }


        self::log(__LINE__);

        // Render recaptch form
        $site_key = $this->getSystemSetting("site-key");
        $recaptcha_message = $this->getProjectSetting("recaptcha-message");

        ?>
            <form id="frm" method="POST">
                <p class="instructions text-center"><?php echo $recaptcha_message; ?></p>
                <div class="g-recaptcha"></div>
                <div class="text-center">
                    <br>
                    <button class="btn btn-primary" type="submit">Submit</button>
                </div>
            </form>

            <script type="text/javascript">
                var onloadCallback = function() {
                    var e = $('.g-recaptcha')[0];
                    grecaptcha.render(e, {
                        'sitekey' : '<?php echo $site_key; ?>'
                    });
                    $('#container').fadeIn();

                };
            </script>

            <script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit"
                    async defer>
            </script>

            <style>
                .instructions {
                    font-weight: bold;
                }

                .invalid-response {
                    font-size: 16pt;
                    font-weight: bold;
                }
                #container {
                    display:none;
                    margin: 50px 0;
                    border-radius: 15px;
                }
                #pagecontent {
                    padding: 20px 10px;
                }


                .g-recaptcha > div{
                    margin 10px auto !important;
                    text-align: center;
                    width: auto !important;
                    height: auto !important;
                }
            </style>
        <?php


        // Prevent the rest of the survey page from being rendered until we verify recaptcha
        \ExternalModules\ExternalModules::exitAfterHook();


        // self::log(__METHOD__, "Stopping");
        // return false;


    }


    # defines criteria to judge someone is on a development box or not
    public static function isDev()
    {
        $is_localhost  = ( @$_SERVER['HTTP_HOST'] == 'localhost' );
        $is_dev_server = ( isset($GLOBALS['is_development_server']) && $GLOBALS['is_development_server'] == '1' );
        $is_dev = ( $is_localhost || $is_dev_server ) ? 1 : 0;
        return $is_dev;
    }


    // Log Wrapper
    public static function log() {
        if (self::isDev()) {
            if (class_exists("Stanford\REDCaptcha\Util")) {
                call_user_func_array("Stanford\REDCaptcha\Util::log", func_get_args());
            }
        } else {
            error_log("NA");
        }
    }

}