<?php
/**
 * Belloo-X
 * 
 * Dating software co-developed by the community.
 * The script is still unstable and still vulnerable.
 * You use at your own risk!
 * 
 * @version X 0.1
 * @link https://github.com/Sh4d0v/belloo-x
 * @todo security fixes
 */
session_cache_limiter("none");
session_start();
include "config.php";
require "PHPMailer/Exception.php";
require "PHPMailer/PHPMailer.php";
require "PHPMailer/SMTP.php";
require "pusher.php";

// Establish a connection to a MySQL database using the mysqli extension.
// If the connection fails, the script exits and displays the error message.
$mysqli = new mysqli($db_host, $db_username, $db_password, $db_name);
if (mysqli_connect_errno()) {
    exit(mysqli_connect_error());
}
$mysqli->set_charset("utf8mb4");

// Stores site configurations to variables for later use
$sm = [];
$sm["plugins"] = sitePlugins();
$sm["plugin"] = sitePlugin();
$sm["settings"] = siteSettings();
$sm["creditsPackages"] = getArray("config_credits", "", "id asc");
$sm["premiumPackages"] = getArray("config_premium", "", "id asc");

date_default_timezone_set($sm["plugins"]["settings"]["timezone"]);
$cronData = getArray("cron", "", "cron DESC");

// Force HTTPS if the 'forceSSL' setting is enabled
if ($sm["plugins"]["settings"]["forceSSL"] == "Yes") {
    $site_url = str_replace("http://", "https://", $site_url);
    if ($_SERVER["HTTPS"] != "on") {
        header("Location: https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]);
        exit();
    }
}

$options = ["encrypted" => false, "cluster" => $sm["plugins"]["pusher"]["cluster"]];
$sm["push"] = new Pusher($sm["plugins"]["pusher"]["key"], $sm["plugins"]["pusher"]["secret"], $sm["plugins"]["pusher"]["id"], @$options); // todo: fix pusher and $options
$sm["price"] = sitePrices();
$sm["basic"] = siteAccountsBasic();
$sm["premium"] = siteAccountsPremium();
$sm["config_email"] = configEmail();
$mobile = false;
$sm["mobile"] = 0;
$sm["config"]["name"] = $sm["plugins"]["settings"]["siteName"];

$https = isSecure();

$check_bar = substr($site_url, -1);
$sm["config"]["site_url"] = rtrim($site_url, '/') . '/';
$sm["client"] = json_decode(getData("client", "client", ""), true);
$wl = ["127.0.0.1", "localhost", "www.localhost", "localhost"];
$sm["admin_ajax"] = false;
$sm["version"] = $sm["settings"]["currentVersion"];
$sm["config"]["like_back"] = siteconfig("like_back");
$sm["config"]["visit_back"] = siteconfig("visit_back");
$sm["config"]["domain"] = preg_replace("/www\\./i", "", $_SERVER["SERVER_NAME"]);
$sm["config"]["theme"] = $sm["settings"]["desktopTheme"];
$sm["config"]["currency"] = $sm["plugins"]["settings"]["currency"];

if (isset($_GET["preset"])) {
    $checkPreset = checkIfExist("theme_preset", "preset", $_GET["preset"]);
    if ($checkPreset == 0) {
        header("Location: ".$site_url);
        exit();
    }
    $_SESSION["preset"] = $_GET["preset"];
}

if (!isset($_SESSION["preset"])) {
    $_SESSION["preset"] = $sm["settings"]["desktopThemePreset"];
}

if (isset($_GET["landingPreset"])) {
    $checkPreset = checkIfExist("theme_preset", "preset", $_GET["landingPreset"]);
    if ($checkPreset == 0) {
        header("Location: ".$site_url);
        exit();
    }
    $_SESSION["landingPreset"] = $_GET["landingPreset"];
}

if (!isset($_SESSION["landingPreset"])) {
    $_SESSION["landingPreset"] = $sm["settings"]["landingThemePreset"];
}

if (isset($_GET["landing"])) {
    $checkLanding = checkIfExist("config_themes", "folder", $_GET["landing"]);
    if ($checkLanding == 0) {
        header("Location: ".$site_url);
        exit();
    }
    $landingTheme = $_GET["landing"];
} else {
    $_SESSION["landingPreset"] = $sm["settings"]["landingThemePreset"];
}

if (!isset($_GET["landing"])) {
    $landingTheme = $sm["settings"]["landingTheme"];
}

$sm["config"]["landing"] = $landingTheme;
$themeFilter = "WHERE theme =\"".$sm["settings"]["desktopTheme"]."\" AND preset = \"".$_SESSION["preset"]."\"";
$sm["theme"] = json_decode(getData("theme_preset", "theme_settings", $themeFilter), true);
$landingThemeFilter = "WHERE theme =\"".$landingTheme."\" AND preset = \"".$_SESSION["landingPreset"]."\"";
$sm["landing"] = json_decode(getData("theme_preset", "theme_settings", $landingThemeFilter), true);
$sm["liveTheme"] = true;
$themeLivePreset = $sm["settings"]["desktopThemePreset"];

if ($_SESSION["preset"] != $themeLivePreset) {
    $sm["liveTheme"] = false;
}

$sm["config"]["fb_app_ok"] = 1;
$sm["config"]["theme_mobile"] = $sm["settings"]["mobileTheme"];
$sm["config"]["theme_email"] = $sm["settings"]["emailTheme"];
$sm["config"]["theme_landing"] = $sm["config"]["landing"];
$sm["config"]["logo"] = $sm["theme"]["logo"]["val"];
$sm["config"]["title"] = siteconfig("title");
$sm["config"]["description"] = siteconfig("description");
$sm["config"]["keywords"] = siteconfig("keywords");
$sm["config"]["lang"] = $sm["plugins"]["settings"]["defaultLang"];
$sm["config"]["email"] = $sm["plugins"]["settings"]["siteEmail"];
$sm["config"]["admin_url"] = $site_url."administrator";
$sm["config"]["theme_url"] = $site_url."themes/".$sm["config"]["theme"];
$sm["config"]["theme_url_landing"] = $site_url."themes/".$sm["config"]["theme_landing"];
$sm["config"]["theme_url_mobile"] = $site_url."themes/".$sm["config"]["theme_mobile"];
$sm["config"]["theme_url_email"] = $site_url."themes/".$sm["config"]["theme_email"];
$sm["config"]["ajax_path"] = $site_url."requests";
$sm["config"]["max_upload"] = getMaximumFileUploadSize();
$sm["genders"] = siteGenders(1);
$sm["interests"] = getSiteInterests();
$sm["lang"] = siteLang($sm["config"]["lang"]);
$sm["alang"] = applang($sm["config"]["lang"]);
$sm["elang"] = emaillang($sm["config"]["lang"]);
$sm["seoLang"] = seolang($sm["config"]["lang"]);
$sm["landingLang"] = landinglang($sm["config"]["lang"], $landingTheme, $_SESSION["landingPreset"]);

if (!isset($_SESSION["lang"])) {
    $_SESSION["lang"] = $sm["config"]["lang"];
}

// Redirects the user to a URL without the "www." subdomain, 
// preserving the protocol (HTTP or HTTPS) and the rest of the URL.
if (substr($_SERVER["HTTP_HOST"], 0, 4) === "www.") {
    $protocol = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
    $url = $protocol.str_replace("www.", "", $_SERVER["HTTP_HOST"]).$_SERVER["REQUEST_URI"];
    header("Location: ".$url);
    exit;
}

$logged = false;
$user = [];
$available_languages = availableLanguages();

if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
    $langs = prefered_language($available_languages, $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
} else {
    $langs = prefered_language($available_languages, "pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7");
}

$lang = key($langs);

if ($lang != "" && $sm["plugins"]["settings"]["browserLanguage"] == "Yes") {
    $_SESSION["lang"] = checkUserLang($lang);
    $sm["lang"] = siteLang(checkUserLang($lang));
    $sm["alang"] = applang(checkUserLang($lang));
    $sm["elang"] = emaillang(checkUserLang($lang));
    $sm["seoLang"] = seolang(checkUserLang($lang));
    $sm["landingLang"] = landinglang(checkUserLang($lang), $landingTheme, $_SESSION["landingPreset"]);
} else {
    $sm["lang"] = siteLang($sm["config"]["lang"]);
    $sm["alang"] = applang($sm["config"]["lang"]);
    $sm["elang"] = emaillang($sm["config"]["lang"]);
    $sm["seoLang"] = seolang($sm["config"]["lang"]);
    $sm["landingLang"] = landinglang($sm["config"]["lang"], $landingTheme, $_SESSION["landingPreset"]);
}

if (isset($_SESSION["user"]) && is_numeric($_SESSION["user"]) && 0 < $_SESSION["user"]) {
    setcookie("user", $_SESSION["user"], time() + 86400);
    getUserInfo($_SESSION["user"], 0);
    checkUserPremium($_SESSION["user"]);

    $sm["user_notifications"] = userNotifications($_SESSION["user"]);
    $sm["lang"] = siteLang($sm["user"]["lang"]);
    $sm["alang"] = applang($sm["user"]["lang"]);
    $sm["elang"] = emaillang($sm["user"]["lang"]);
    $sm["seoLang"] = seolang($sm["user"]["lang"]);
    $sm["landingLang"] = landinglang($sm["user"]["lang"], $landingTheme, $_SESSION["landingPreset"]);
    $sm["genders"] = siteGenders($sm["user"]["lang"]);

    $modPermission = [];
    if($sm["user"]["admin"] >= 1) {
	    $moderationList = getArray("moderation_list","","moderation ASC");
	    foreach ($moderationList as $mod) {  
	        if($sm["user"]["admin"] == 1) {
	            $modPermission[$mod["moderation"]] = "Yes";
	        } else {
	            $modVal = getData('moderators_permission','setting_val',"WHERE setting = '".$mod["moderation"]."' AND id = '".$sm["user"]["moderator"]."'");
	            $modPermission[$mod["moderation"]] = $modVal;
	        }
	    }
    }
    $sm["moderator"] = $modPermission;

    $time = time();
    $logged = true;
    $ip = getUserIpAddr();

    if ($sm["user"]["ip"] != $ip) {
        $mysqli->query("UPDATE users set ip = '".$ip."' where id = '".$_SESSION["user"]."'");
    }
    if ($sm["user"]["last_access"] < $time || $sm["user"]["last_access"] == 0) {
        $mysqli->query("UPDATE users set last_access = '".$time."' where id = '".$_SESSION["user"]."'");
    }
}
$sm["logged"] = $logged;

require_once "custom/app_core.php";

if (!empty($_GET["lang"])) {
    $slang = secureEncode($_GET["lang"]);
    $_SESSION["lang"] = $slang;
    $sm["lang"] = siteLang($_SESSION["lang"]);
    $sm["alang"] = applang($_SESSION["lang"]);
    $sm["elang"] = emaillang($_SESSION["lang"]);
    $sm["genders"] = siteGenders($_SESSION["lang"]);
    $sm["seoLang"] = seolang($_SESSION["lang"]);
    $sm["landingLang"] = landinglang($_SESSION["lang"], $landingTheme, $_SESSION["landingPreset"]);

    if ($logged) {
        $mysqli->query("UPDATE users SET lang = '".$slang."' WHERE id = '".$user_id."'");
    }
}

if (!$logged) {
    unset($_SESSION["user"]);
    unset($user);
}

function appLang($lang) {
    global $mysqli, $sm;
    $result = [];
    $lang = secureEncode($lang);

    $query = $mysqli->query("SELECT * FROM app_lang where lang_id = '".$lang."' ORDER BY id ASC");

    while ($row = $query->fetch_assoc()) {
        $result[$row["id"]] = ["id" => $row["id"], "text" => $row["text"]];
    }
    return $result;
}

function themeSetting($theme) {
    global $mysqli, $sm;
    $result = [];

    $query = $mysqli->query("SELECT * FROM theme_settings where theme = '".$theme."'");

    while ($row = $query->fetch_assoc()) {
        $result[$row["setting"]] = ["val" => $row["setting_val"]];
    }
    return $result;
}

function sitePlugins() {
    global $mysqli, $sm;
    $result = [];

    $query = $mysqli->query("SELECT * FROM plugins_settings");

    while ($row = $query->fetch_assoc()) {
        $filter = "where setting = \"".$row["setting"]."\" and plugin = \"".$row["plugin"]."\"";
        $clientSetting = getData("plugins_settings_values", "setting_val", $filter);

        if ($clientSetting != "noData") {
            $row["setting_val"] = $clientSetting;
        }

        if (isset($result[$row["plugin"]])) {
            $result[$row["plugin"]][$row["setting"]] = $row["setting_val"];
        } else {
            $result[$row["plugin"]] = [$row["setting"] => $row["setting_val"]];
        }
    }
    return $result;
}

/**
 * Retrieves all plugins from the database.
 */
function sitePlugin() {
    global $mysqli, $sm;
    $result = [];

    $query = $mysqli->query("SELECT * FROM plugins");

    while ($row = $query->fetch_assoc()) {
        $result[] = $row;
    }
    return $result;
}

/**
 * Checks if the current connection is secure. Return true of false.
 */
function isSecure() {
	return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || $_SERVER["SERVER_PORT"] == 443;
}

function siteSettings() {
    global $mysqli, $sm;
    $result = [];

    $query = $mysqli->query("SELECT * FROM settings");

    while ($row = $query->fetch_assoc()) {
        $result[$row["setting"]] = $row["setting_val"];
    }
    return $result;
}

function emailLang($lang) {
    global $mysqli, $sm;
    $result = [];
    $lang = secureEncode($lang);

    $query = $mysqli->query("SELECT * FROM email_lang where lang_id = '".$lang."' ORDER BY id ASC");

    while ($row = $query->fetch_assoc()) {
        $result[$row["id"]] = ["id" => $row["id"], "text" => $row["text"]];
    }
    return $result;
}

function seoLang($lang) {
    global $mysqli, $sm;
    $result = [];
    $lang = secureEncode($lang);

    $query = $mysqli->query("SELECT * FROM seo_lang where lang_id = '".$lang."' ORDER BY page ASC");
    
    while ($row = $query->fetch_assoc()) {
        $result[$row["page"]][$row["id"]] = ["text" => $row["text"]];
    }
    return $result;
}

/**
 * Retrieves the landing page language data for a given language strings from database.
 */
function landingLang($lang, $theme, $preset) {
    global $mysqli, $sm;
    $result = [];
    $lang = secureEncode($lang);

    $query = $mysqli->query("SELECT * FROM landing_lang where lang_id = '".$lang."' and theme = '".$theme."' and preset = '".$preset."' ORDER BY id ASC");

    while ($row = $query->fetch_assoc()) {
        $result[$row["id"]] = ["id" => $row["id"], "text" => $row["text"]];
    }
    return $result;
}

/**
 * Retrieves a specific value from the site configuration.
 */
function siteConfig($val) {
    global $mysqli, $sm;

    $config = $mysqli->query("SELECT * FROM config");
    $result = $config->fetch_object();
    return $result->$val;
}