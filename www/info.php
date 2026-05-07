<?php
	ob_start();
	phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
    $phpinfo = array();

	$parts = preg_split('~(<[^>]+>)~', ob_get_clean(), -1, PREG_SPLIT_DELIM_CAPTURE);

    var_dump($parts);