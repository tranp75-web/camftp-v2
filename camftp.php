<?php
/**
 * CamFTP V2
 *
 * Universal camera snapshot uploader
 *
 * Compatible CamFTP V1 / Eedomus
 *
 * PHP 8.1+
 */


declare(strict_types=1);


const CAMFTP_VERSION = '2.0.0';


/*
|--------------------------------------------------------------------------
| Initialisation
|--------------------------------------------------------------------------
*/

ini_set('memory_limit', '512M');

$configFile = __DIR__ . '/config.php';


if (!file_exists($configFile)) {
    fail('Configuration absente');
}


$config = require $configFile;


date_default_timezone_set(
    $config['timezone'] ?? 'Europe/Paris'
);


/*
|--------------------------------------------------------------------------
| Exécution principale
|--------------------------------------------------------------------------
*/

try {

    $params = getParameters($_GET, $config);


    $servers = [];


    if ($params['numcam'] === 99) {

        $central = $config['centralServer'];

        $servers[$central] = connectServer(
            $config['servers'][$central]
        );

    } else {


        if ($params['numftp1'] > 0) {

            $servers[$params['numftp1']] = connectServer(
                $config['servers'][$params['numftp1']]
            );

        }


        if (
            $params['numftp2'] > 0 &&
            $params['numftp2'] !== $params['numftp1']
        ) {

            $servers[$params['numftp2']] = connectServer(
                $config['servers'][$params['numftp2']]
            );

        }

    }



    executeCapture(
        $params,
        $config,
        $servers
    );



    foreach ($servers as $connection) {

        disconnectServer($connection);

    }


    success();



} catch (Throwable $e) {


    /*
     * Pas de journalisation demandée.
     * On retourne uniquement un état compatible V1.
     */

    fail($e->getMessage());

}




/*
|--------------------------------------------------------------------------
| Gestion erreurs
|--------------------------------------------------------------------------
*/


function success(): never
{
    echo "1";
    exit;
}



function fail(string $message = ''): never
{
    http_response_code(500);

    echo "0";

    exit;
}





/*
|--------------------------------------------------------------------------
| Lecture et validation GET
|--------------------------------------------------------------------------
*/


function getParameters(
    array $input,
    array $config
): array {


    $cameraCount = count($config['cameras']);

    $serverCount = count($config['servers']);


    $numcam = isset($input['numcam'])
        ? (int)$input['numcam']
        : 99;


    if (
        $numcam !== 99 &&
        (
            $numcam < 1 ||
            $numcam > $cameraCount
        )
    ) {

        throw new Exception('Caméra invalide');

    }



    $numftp1 = isset($input['numftp1'])
        ? (int)$input['numftp1']
        : 0;


    if (
        $numftp1 < 0 ||
        $numftp1 > $serverCount
    ) {

        throw new Exception('FTP1 invalide');

    }




    $numftp2 = isset($input['numftp2'])
        ? (int)$input['numftp2']
        : 0;


    if (
        $numftp2 < 0 ||
        $numftp2 > $serverCount
    ) {

        throw new Exception('FTP2 invalide');

    }




    $maxSnapshots =
        $config['limits']['maxSnapshots'] ?? 100;


    $nbsnap = isset($input['nbsnap'])
        ? (int)$input['nbsnap']
        : 1;


    if (
        $nbsnap < 1 ||
        $nbsnap > $maxSnapshots
    ) {

        throw new Exception('Nombre snapshots invalide');

    }




    $maxDelay =
        $config['limits']['maxDelay'] ?? 60;


    $updelay = isset($input['updelay'])
        ? (int)$input['updelay']
        : 1;


    if (
        $updelay < 0 ||
        $updelay > $maxDelay
    ) {

        throw new Exception('Délai invalide');

    }




    $getmail = isset($input['getmail']) &&
               (int)$input['getmail'] === 1;



    return [

        'numcam'  => $numcam,

        'numftp1' => $numftp1,

        'numftp2' => $numftp2,

        'nbsnap'  => $nbsnap,

        'updelay' => $updelay,

        'getmail' => $getmail,

    ];

}
/*
|--------------------------------------------------------------------------
| Connexions serveurs
|--------------------------------------------------------------------------
*/


function connectServer(array $server): array
{

    $type = strtolower(
        $server['type'] ?? 'ftp'
    );


    switch ($type) {


        case 'ftp':

            return connectFTP($server, false);



        case 'ftps':

            return connectFTP($server, true);



        case 'sftp':

            return connectSFTP($server);



        default:

            throw new Exception(
                'Type serveur inconnu'
            );

    }

}




/*
|--------------------------------------------------------------------------
| FTP / FTPS
|--------------------------------------------------------------------------
*/


function connectFTP(
    array $server,
    bool $ssl = false
): array {


    $host =
        $server['host'] ?? '';

    $port =
        $server['port'] ?? 21;



    if ($ssl) {

        if (!function_exists('ftp_ssl_connect')) {

            throw new Exception(
                'FTPS indisponible'
            );

        }


        $connection = ftp_ssl_connect(
            $host,
            $port,
            10
        );


    } else {


        $connection = ftp_connect(
            $host,
            $port,
            10
        );

    }



    if (!$connection) {

        throw new Exception(
            'Connexion FTP impossible'
        );

    }




    $login = ftp_login(
        $connection,
        $server['user'],
        $server['password']
    );



    if (!$login) {

        ftp_close($connection);

        throw new Exception(
            'Authentification FTP impossible'
        );

    }



    ftp_pasv(
        $connection,
        $server['passive'] ?? true
    );



    if (
        !empty($server['path'])
    ) {

        if (
            !ftp_chdir(
                $connection,
                $server['path']
            )
        ) {

            throw new Exception(
                'Répertoire FTP inaccessible'
            );

        }

    }



    return [

        'type' => $ssl ? 'ftps' : 'ftp',

        'connection' => $connection,

    ];

}





/*
|--------------------------------------------------------------------------
| SFTP
|--------------------------------------------------------------------------
|
| Nécessite phpseclib v3
|
| composer require phpseclib/phpseclib
|
*/


function connectSFTP(
    array $server
): array {


    if (
        !class_exists(
            '\\phpseclib3\\Net\\SFTP'
        )
    ) {

        throw new Exception(
            'phpseclib absent pour SFTP'
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

        throw new Exception(
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

            throw new Exception(
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
| Upload fichier
|--------------------------------------------------------------------------
*/


function uploadSnapshot(
    array $server,
    string $filename,
    string $content
): bool {


    switch ($server['type']) {


        case 'ftp':

        case 'ftps':


            $stream = fopen(
                'php://memory',
                'r+'
            );


            fwrite(
                $stream,
                $content
            );


            rewind($stream);



            $result = ftp_fput(
                $server['connection'],
                $filename,
                $stream,
                FTP_BINARY
            );


            fclose($stream);



            return $result;




        case 'sftp':


            return $server['connection']
                ->put(
                    $filename,
                    $content
                );




        default:

            throw new Exception(
                'Protocole upload inconnu'
            );

    }

}




/*
|--------------------------------------------------------------------------
| Fermeture connexions
|--------------------------------------------------------------------------
*/


function disconnectServer(
    array $server
): void {


    switch ($server['type']) {


        case 'ftp':

        case 'ftps':

            ftp_close(
                $server['connection']
            );

            break;



        case 'sftp':

            // phpseclib ferme automatiquement

            break;


    }

}
/*
|--------------------------------------------------------------------------
| Workflow principal
|--------------------------------------------------------------------------
*/


function executeCapture(
    array $params,
    array $config,
    array $servers
): void {


    $index = 1000;


    $datetime = date(
        'YmdHis'
    );


    $count = 0;



    while (
        $count < $params['nbsnap']
    ) {


        if (
            $params['numcam'] === 99
        ) {


            /*
             * Mode historique V1 :
             *
             * toutes les caméras
             * vers le serveur central
             */


            foreach (
                $config['cameras']
                as $cameraId => $camera
            ) {


                processCamera(
                    $camera,
                    $servers,
                    $config,
                    $datetime,
                    $index
                );


                $index++;

            }



        } else {


            /*
             * Une seule caméra
             */

            $camera =
                $config['cameras']
                [$params['numcam']];


            processCamera(
                $camera,
                $servers,
                $config,
                $datetime,
                $index
            );


            $index++;


        }



        $count++;



        if (
            $params['nbsnap'] > 1 &&
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
    string $datetime,
    int $index
): void {


    /*
     * Téléchargement unique
     */

    $image =
        downloadSnapshot(
            $camera
        );



    if (
        !empty($config['options']['check_jpeg'])
    ) {


        if (
            !isJPEG($image)
        ) {

            throw new Exception(
                'Image JPEG invalide'
            );

        }

    }




    $filename =
        buildFilename(
            $camera['name'],
            $datetime,
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




    /*
     * Mail traité en partie 4
     */

}





/*
|--------------------------------------------------------------------------
| Capture caméra
|--------------------------------------------------------------------------
*/


function downloadSnapshot(
    array $camera
): string {


    $context =
        stream_context_create([

            'http' => [

                'timeout' =>
                    $camera['timeout'] ?? 10,

            ]

        ]);



    $image =
        file_get_contents(
            $camera['url'],
            false,
            $context
        );



    if (
        $image === false
    ) {

        throw new Exception(
            'Capture caméra impossible : '
            . $camera['name']
        );

    }



    return $image;

}





/*
|--------------------------------------------------------------------------
| Validation JPEG
|--------------------------------------------------------------------------
*/


function isJPEG(
    string $data
): bool {


    return substr(
        $data,
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
    string $datetime,
    int $index,
    array $config
): string {


    $template =
        $config['filename']
        ??
        '{camera}_{datetime}_{index}.jpg';



    return str_replace(

        [

            '{camera}',

            '{datetime}',

            '{timestamp}',

            '{index}',

        ],

        [

            $camera,

            $datetime,

            time(),

            $index,

        ],

        $template

    );

}
