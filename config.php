<?php
/**
 * Configuration CamFTP
 */

date_default_timezone_set('Europe/Paris');

/*****************************************************
 * Caméras
 *****************************************************/
 
$snapshot = [

    1 => [
        'name' => 'Camera_salon',
        'url'  => 'http://XX.YY.ZZ.AA:8051/image/jpeg.cgi',
        'user' => 'admin2',
        'pass' => 'xxxxxx'
    ],

    2 => [
        'name' => 'Camera_sallemanger',
        'url'  => 'http://XXX.XXX.XXX.XXX:8052/cgi/jpg/image.cgi',
        'user' => 'admin2',
        'pass' => 'YYYYY'
    ],

    3 => [
        'name' => 'Camera_garage',
        'url'  => 'http://XXX.XXX.XXX.XXX:8053/cgi/jpg/image.cgi',
        'user' => 'admin2',
        'pass' => 'XXXXX'
    ],

    4 => [
        'name' => 'Camera_entree',
        'url'  => 'http://XXX.XXX.XXX.XXX:8054/api.cgi?cmd=Snap&channel=0&width=640&height=480&_dc=1725390210',
        'user' => 'admin',
        'pass' => 'XXXXX'
    ],

];


/*****************************************************
 * Serveurs FTP
 *****************************************************/
$ftp = [

    1 => [
        'server' => '192.168.2.3',
        'user'   => 'camera',
        'pwd'    => 'AAAAAAAA',
        'path'   => 'Recordings/garage'
    ],

    5 => [
        'server' => '192.168.2.3',
        'user'   => 'camera',
        'pwd'    => 'AAAAAAAA',
        'path'   => 'Recordings/salon'
    ],

    6 => [
        'server' => '192.168.2.3',
        'user'   => 'camera',
        'pwd'    => 'AAAAAAAA',
        'path'   => 'Recordings/SalleaManger'
    ],

    7 => [
        'server' => '192.168.2.3',
        'user'   => 'camera',
        'pwd'    => 'AAAAAAAA',
        'path'   => 'Recordings/garage'
    ],

    8 => [
        'server' => '192.168.2.3',
        'user'   => 'camera',
        'pwd'    => 'AAAAAAAA',
        'path'   => 'Recordings/entree'
    ],

];

/*****************************************************
 * FTP central
 *****************************************************/

$ftpcentral = 1;