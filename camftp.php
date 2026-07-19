<?php
declare(strict_types=1);

/**
 * CamFTP V2.0.0
 *
 * Capture de snapshots caméra IP
 * Transfert FTP / FTPS / SFTP
 *
 * PHP 8.1+
 */


/*
|--------------------------------------------------------------------------
| Autoload SFTP (phpseclib v3 optionnel)
|--------------------------------------------------------------------------
*/

$autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}


/*
|--------------------------------------------------------------------------
| Constantes
|--------------------------------------------------------------------------
*/

const CAMFTP_VERSION = '2.0.0';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$configFile = __DIR__ . '/config.php';


if (!file_exists($configFile)) {
    fail();
}


$config = require $configFile;


if (!is_array($config)) {
    fail();
}


date_default_timezone_set(
    $config['timezone'] ?? 'Europe/Paris'
);



/*
|--------------------------------------------------------------------------
| Programme principal
|--------------------------------------------------------------------------
*/

try {


    $params = getParameters(
        $_GET,
        $config
    );


    $servers = [];


    /*
     * Sélection des serveurs
     */

    if ($params['numcam'] === 99) {


        if (
            !isset(
                $config['servers']
                [$config['centralServer']]
            )
        ) {
            throw new RuntimeException(
                'Serveur central absent'
            );
        }


        $id = $config['centralServer'];


        $servers[$id] =
            connectServer(
                $config['servers'][$id]
            );


    } else {


        if ($params['numftp1'] > 0) {

            $id = $params['numftp1'];

            $servers[$id] =
                connectServer(
                    $config['servers'][$id]
                );
        }


        if (
            $params['numftp2'] > 0 &&
            $params['numftp2'] !== $params['numftp1']
        ) {

            $id = $params['numftp2'];

            $servers[$id] =
                connectServer(
                    $config['servers'][$id]
                );
        }

    }


    if (empty($servers)) {

        throw new RuntimeException(
            'Aucune destination'
        );

    }



    try {


        executeCapture(
            $params,
            $config,
            $servers
        );


    } finally {


        foreach ($servers as $server) {

            disconnectServer(
                $server
            );

        }

    }


    success();



} catch (Throwable $e) {


    /*
     * Aucun log volontairement.
     * Retour compatible V1.
     */

    fail();

}



/*
|--------------------------------------------------------------------------
| Gestion des résultats
|--------------------------------------------------------------------------
*/

function success(): never
{
    echo '1';
    exit;
}



function fail(): never
{
    echo '0';
    exit;
}




/*
|--------------------------------------------------------------------------
| Validation paramètres GET
|--------------------------------------------------------------------------
*/

function getParameters(
    array $input,
    array $config
): array {


    $cameraCount =
        count(
            $config['cameras'] ?? []
        );


    $serverCount =
        count(
            $config['servers'] ?? []
        );



    $numcam =
        isset($input['numcam'])
        ? filter_var(
            $input['numcam'],
            FILTER_VALIDATE_INT
        )
        : 99;



    if (
        $numcam === false ||
        (
            $numcam !== 99 &&
            (
                $numcam < 1 ||
                $numcam > $cameraCount
            )
        )
    ) {

        throw new RuntimeException(
            'Caméra invalide'
        );

    }




    $numftp1 =
        getIntParameter(
            $input,
            'numftp1',
            0
        );


    $numftp2 =
        getIntParameter(
            $input,
            'numftp2',
            0
        );



    if (
        $numftp1 < 0 ||
        $numftp1 > $serverCount ||
        $numftp2 < 0 ||
        $numftp2 > $serverCount
    ) {

        throw new RuntimeException(
            'Serveur invalide'
        );

    }



    $maxSnapshots =
        $config['limits']['maxSnapshots']
        ?? 100;



    $nbsnap =
        getIntParameter(
            $input,
            'nbsnap',
            1
        );



    if (
        $nbsnap < 1 ||
        $nbsnap > $maxSnapshots
    ) {

        throw new RuntimeException(
            'Nombre snapshots invalide'
        );

    }



    $maxDelay =
        $config['limits']['maxDelay']
        ?? 60;



    $updelay =
        getIntParameter(
            $input,
            'updelay',
            0
        );



    if (
        $updelay < 0 ||
        $updelay > $maxDelay
    ) {

        throw new RuntimeException(
            'Délai invalide'
        );

    }



    return [

        'numcam'  => $numcam,

        'numftp1' => $numftp1,

        'numftp2' => $numftp2,

        'nbsnap'  => $nbsnap,

        'updelay' => $updelay,

    ];

}



function getIntParameter(
    array $input,
    string $name,
    int $default
): int {


    if (!isset($input[$name])) {

        return $default;

    }


    $value =
        filter_var(
            $input[$name],
            FILTER_VALIDATE_INT
        );


    if ($value === false) {

        throw new RuntimeException(
            "Paramètre invalide : $name"
        );

    }


    return $value;

}
/*
|--------------------------------------------------------------------------
| Connexions FTP / FTPS / SFTP
|--------------------------------------------------------------------------
*/


function connectServer(
    array $server
): array {


    if (
        !isset($server['type'])
    ) {

        throw new RuntimeException(
            'Type serveur absent'
        );

    }



    return match (
        strtolower($server['type'])
    ) {


        'ftp' =>
            connectFTP(
                $server,
                false
            ),


        'ftps' =>
            connectFTP(
                $server,
                true
            ),


        'sftp' =>
            connectSFTP(
                $server
            ),


        default =>
            throw new RuntimeException(
                'Protocole inconnu'
            ),

    };

}




/*
|--------------------------------------------------------------------------
| FTP / FTPS
|--------------------------------------------------------------------------
*/


function connectFTP(
    array $server,
    bool $ssl
): array {


    $host =
        $server['host'] ?? '';

    $port =
        $server['port'] ?? 21;



    if ($ssl) {


        if (
            !function_exists(
                'ftp_ssl_connect'
            )
        ) {

            throw new RuntimeException(
                'FTPS non disponible'
            );

        }


        $ftp =
            ftp_ssl_connect(
                $host,
                $port,
                10
            );


    } else {


        $ftp =
            ftp_connect(
                $host,
                $port,
                10
            );

    }



    if (!$ftp) {

        throw new RuntimeException(
            'Connexion FTP impossible'
        );

    }



    if (
        !ftp_login(
            $ftp,
            $server['user'] ?? '',
            $server['password'] ?? ''
        )
    ) {


        ftp_close($ftp);


        throw new RuntimeException(
            'Authentification FTP impossible'
        );

    }



    ftp_pasv(
        $ftp,
        $server['passive'] ?? true
    );



    if (
        !empty($server['path'])
    ) {


        if (
            !ftp_chdir(
                $ftp,
                $server['path']
            )
        ) {


            ftp_close($ftp);


            throw new RuntimeException(
                'Répertoire FTP inaccessible'
            );

        }

    }



    return [

        'type' => $ssl ? 'ftps' : 'ftp',

        'connection' => $ftp,

    ];

}





/*
|--------------------------------------------------------------------------
| SFTP
|--------------------------------------------------------------------------
*/


function connectSFTP(
    array $server
): array {


    if (
        !class_exists(
            '\\phpseclib3\\Net\\SFTP'
        )
    ) {


        throw new RuntimeException(
            'phpseclib SFTP absent'
        );

    }



    $sftp =
        new \phpseclib3\Net\SFTP(
            $server['host'],
            $server['port'] ?? 22
        );



    if (
        !$sftp->login(
            $server['user'],
            $server['password']
        )
    ) {


        throw new RuntimeException(
            'Connexion SFTP impossible'
        );

    }



    if (
        !empty($server['path'])
    ) {


        if (
            !$sftp->chdir(
                $server['path']
            )
        ) {


            throw new RuntimeException(
                'Répertoire SFTP inaccessible'
            );

        }

    }



    return [

        'type' => 'sftp',

        'connection' => $sftp,

    ];

}





/*
|--------------------------------------------------------------------------
| Upload
|--------------------------------------------------------------------------
*/


function uploadSnapshot(
    array $server,
    string $filename,
    string $content
): void {


    switch (
        $server['type']
    ) {



        case 'ftp':

        case 'ftps':


            $stream =
                fopen(
                    'php://memory',
                    'r+'
                );


            if (!$stream) {

                throw new RuntimeException(
                    'Mémoire temporaire impossible'
                );

            }



            fwrite(
                $stream,
                $content
            );


            rewind($stream);



            $result =
                ftp_fput(
                    $server['connection'],
                    $filename,
                    $stream,
                    FTP_BINARY
                );


            fclose($stream);



            if (!$result) {

                throw new RuntimeException(
                    'Upload FTP impossible'
                );

            }


            break;




        case 'sftp':


            if (
                !$server['connection']->put(
                    $filename,
                    $content
                )
            ) {

                throw new RuntimeException(
                    'Upload SFTP impossible'
                );

            }


            break;




        default:


            throw new RuntimeException(
                'Type upload inconnu'
            );

    }

}





/*
|--------------------------------------------------------------------------
| Fermeture connexion
|--------------------------------------------------------------------------
*/


function disconnectServer(
    array $server
): void {


    switch (
        $server['type']
    ) {


        case 'ftp':

        case 'ftps':


            if (
                is_resource(
                    $server['connection']
                )
            ) {

                ftp_close(
                    $server['connection']
                );

            }

            break;



        case 'sftp':

            /*
             * phpseclib ferme automatiquement.
             */

            break;

    }

}
/*
|--------------------------------------------------------------------------
| Traitement principal
|--------------------------------------------------------------------------
*/


function executeCapture(
    array $params,
    array $config,
    array $servers
): void {


    $counter = 0;


    while (
        $counter < $params['nbsnap']
    ) {


        if (
            $params['numcam'] === 99
        ) {


            foreach (
                $config['cameras']
                as $camera
            ) {


                processCamera(
                    $camera,
                    $servers,
                    $config,
                    $counter
                );

            }


        } else {


            if (
                !isset(
                    $config['cameras']
                    [$params['numcam']]
                )
            ) {

                throw new RuntimeException(
                    'Caméra inexistante'
                );

            }


            processCamera(
                $config['cameras'][$params['numcam']],
                $servers,
                $config,
                $counter
            );

        }



        $counter++;



        if (
            $counter < $params['nbsnap'] &&
            $params['updelay'] > 0
        ) {

            sleep(
                $params['updelay']
            );

        }

    }

}





/*
|--------------------------------------------------------------------------
| Traitement d'une caméra
|--------------------------------------------------------------------------
*/


function processCamera(
    array $camera,
    array $servers,
    array $config,
    int $index
): void {


    $image =
        downloadSnapshot(
            $camera
        );



    if (
        ($config['options']['check_jpeg'] ?? true)
        &&
        !isJPEG(
            $image
        )
    ) {

        throw new RuntimeException(
            'Image JPEG invalide'
        );

    }



    $filename =
        buildFilename(
            $camera['name'] ?? 'camera',
            $index,
            $config
        );



    foreach (
        $servers
        as $server
    ) {


        uploadSnapshot(
            $server,
            $filename,
            $image
        );

    }



    unset($image);

}





/*
|--------------------------------------------------------------------------
| Téléchargement snapshot
|--------------------------------------------------------------------------
*/


function downloadSnapshot(
    array $camera
): string {


    if (
        empty($camera['url'])
    ) {

        throw new RuntimeException(
            'URL caméra absente'
        );

    }



    $timeout =
        $camera['timeout']
        ?? 10;



    $context =
        stream_context_create([

            'http' => [

                'timeout' => $timeout,

                'ignore_errors' => true,

            ]

        ]);



    $content =
        file_get_contents(
            $camera['url'],
            false,
            $context
        );



    if (
        $content === false ||
        $content === ''
    ) {

        throw new RuntimeException(
            'Capture caméra impossible'
        );

    }



    return $content;

}





/*
|--------------------------------------------------------------------------
| Contrôle JPEG rapide
|--------------------------------------------------------------------------
*/


function isJPEG(
    string $content
): bool {


    return substr(
        $content,
        0,
        2
    ) === "\xFF\xD8";

}





/*
|--------------------------------------------------------------------------
| Génération nom fichier
|--------------------------------------------------------------------------
*/


function buildFilename(
    string $camera,
    int $index,
    array $config
): string {


    $template =
        $config['filename']
        ??
        '{camera}_{datetime}_{index}.jpg';



    $camera =
        sanitizeFilename(
            $camera
        );



    return str_replace(

        [

            '{camera}',

            '{datetime}',

            '{timestamp}',

            '{index}',

        ],

        [

            $camera,

            date(
                'YmdHis'
            ),

            time(),

            $index + 1,

        ],

        $template

    );

}





/*
|--------------------------------------------------------------------------
| Nettoyage nom fichier
|--------------------------------------------------------------------------
*/


function sanitizeFilename(
    string $value
): string {


    $value =
        preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '_',
            $value
        );



    return trim(
        $value ?? 'camera',
        '_'
    );

}
