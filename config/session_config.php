<?php

$session_lifetime = 60 * 60 * 24 * 30;
// 30 dagen

ini_set(
    "session.gc_maxlifetime",
    (string) $session_lifetime
);
session_name("FITE_SESSION");

session_set_cookie_params([
    "lifetime" => $session_lifetime,
    "path" => "/jarvis/festi-tikkie/",
    "secure" => true,
    "httponly" => true,
    "samesite" => "None",
]);

session_start();