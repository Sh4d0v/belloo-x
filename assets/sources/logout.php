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
 */
if (isset($_SESSION["user"])) {
    unset($_SESSION["user"]);
    setcookie("user", 0, time() - 3600);
}

if (isset($_SESSION["new_user"])) {
    unset($_SESSION["new_user"]);
}

$domain = $_SERVER["SERVER_NAME"];
header("Location: https://".$domain);