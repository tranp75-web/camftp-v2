<?php
/**
 * CamFTP V2
 *
 * Exemple de configuration.
 *
 * Copier ce fichier en config.php
 * et modifier les valeurs.
 */

composer require phpseclib/phpseclib

return [

    /*
     * Général
     */
    'version' => '2.0.0',

    'timezone' => 'Europe/Paris',


    /*
     * Sécurité / limites
     */
    'limits' => [

        // Nombre maximum de snapshots par appel
        'maxSnapshots' => 100,

        // Délai maximum entre captures (secondes)
        'maxDelay' => 60,

    ],


    /*
     * Caméras
     *
     * La clé numérique correspond à numcam
     */
    'cameras' => [

        1 => [

            'name' => 'Camera_Veranda',

            'url' => 'http://user:password@camera-ip/snapshot.jpg',

            // Timeout HTTP en secondes
            'timeout' => 10,

        ],

        2 => [

            'name' => 'Camera_Jardin',

            'url' => 'http://user:password@camera-ip/snapshot.jpg',

            'timeout' => 10,

        ],

    ],


    /*
     * Serveurs de destination
     *
     * Types possibles :
     *
     * ftp
     * ftps
     * sftp
     */
    'servers' => [

        1 => [

            'type' => 'ftp',

            'host' => 'ftp.example.com',

            'port' => 21,

            'user' => 'username',

            'password' => 'password',

            'path' => '',

            'passive' => true,

        ],


        2 => [

            'type' => 'ftps',

            'host' => 'ftps.example.com',

            'port' => 21,

            'user' => 'username',

            'password' => 'password',

            'path' => '',

            'verify_ssl' => true,

        ],


        3 => [

            'type' => 'sftp',

            'host' => 'sftp.example.com',

            'port' => 22,

            'user' => 'username',

            'password' => 'password',

            'path' => '',

        ],

    ],


    /*
     * Serveur utilisé pour :
     *
     * numcam=99
     */
    'centralServer' => 3,


    /*
     * Nom des fichiers
     *
     * Variables disponibles :
     *
     * {camera}
     * {datetime}
     * {timestamp}
     * {index}
     */
    'filename' => '{camera}_{datetime}_{index}.jpg',



    /*
     * Options générales
     */
    'options' => [

        // FTP mode passif
        'passive' => true,

        // Vérification JPEG
        'check_jpeg' => true,

    ],

];
