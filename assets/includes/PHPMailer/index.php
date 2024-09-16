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
$actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://".$_SERVER['HTTP_HOST'];	
header("Location: $actual_link");
return true;