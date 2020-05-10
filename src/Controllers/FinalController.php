<?php

namespace RachidLaasri\LaravelInstaller\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RachidLaasri\LaravelInstaller\Events\LaravelInstallerFinished;
use RachidLaasri\LaravelInstaller\Helpers\EnvironmentManager;
use RachidLaasri\LaravelInstaller\Helpers\FinalInstallManager;
use RachidLaasri\LaravelInstaller\Helpers\InstalledFileManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinalController extends Controller
{
    static $USER_ADMIN = 'admin@mail.com';
    static $CLIENT_DESCRIPTION = 'Token generado automaticamente desde el instalador';
    static $SOURCE_TEMPLATE = 'https://media-mochileros.s3.us-east-2.amazonaws.com';


    /**
     * Generamos token para conexión con el cliente (FRONT)
     */
    private function generateTokenClient()
    {
        $token = Str::random(45);
        try {
            $values = array(
                'token' => 'ec_' . $token,
                'description' => self::$CLIENT_DESCRIPTION,
                'created_at' => date("Y-m-d h:m:s")
            );
            DB::table('client_tokens')
                ->insert($values);
            return $values;
        } catch (\Execption $e) {
            return $e->getMessage();
        }
    }

    /**
     * Metodo publico con el objetivo de extrar la data de la creación del token
     */

    public function getTokenClient()
    {
        try {
            $data = DB::table('client_tokens')->first();
            return $data;
        } catch (\Execption $e) {
            return $e->getMessage();
        }
    }

    /**
     * Update installed file and display finished view.
     *
     * @param \RachidLaasri\LaravelInstaller\Helpers\InstalledFileManager $fileManager
     * @param \RachidLaasri\LaravelInstaller\Helpers\FinalInstallManager $finalInstall
     * @param \RachidLaasri\LaravelInstaller\Helpers\EnvironmentManager $environment
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function finish(InstalledFileManager $fileManager, FinalInstallManager $finalInstall, EnvironmentManager $environment)
    {
        $finalMessages = $finalInstall->runFinal();
        $finalStatusMessage = '';
        $finalEnvFile = $environment->getEnvContent();

        event(new LaravelInstallerFinished);
        $finalToken = $this->generateTokenClient();

        return view('vendor.installer.finished', compact('finalMessages', 'finalStatusMessage', 'finalEnvFile'));
    }

    /**
     * Retornamos un vista con los datos de token
     */
    public function connectionData()
    {
        $data = $this->getTokenClient();
        $newPass = Str::random(8);

        User::where('email', self::$USER_ADMIN)->update(
            ['password' => bcrypt($newPass)]
        );

        $data = array(
            'url' => env('APP_URL', '') . '/api/1.0',
            'token' => $data,
            'user' => [
                'email' => self::$USER_ADMIN,
                'password' => $newPass
            ],
            'fields' => [
                [
                    'name' => 'stripePk',
                    'label' => 'Clave Stripe (pk_)',
                    'help' => 'Ingresa la clave publica de stripe https://dashboard.stripe.com/test/apikeys',
                    'value' => env('STRIPE_KEY', ''),
                    'type' => 'text',
                ],
                [
                    'name' => 'googleId',
                    'label' => 'IDs de cliente de OAuth 2.0',
                    'help' => 'Ingresa el ID de cliente https://console.developers.google.com/apis/credentials?hl=ES',
                    'value' => '',
                    'type' => 'text'
                ],
                [
                    'name' => 'facebookId',
                    'label' => 'Facebook APP ID',
                    'help' => 'Ingresa tu APP ID publica de facebook https://developers.facebook.com/apps/',
                    'value' => '',
                    'type' => 'text'
                ]
            ]

        );
        return view('vendor.installer.final', compact('data'));
    }

    /**
     * Guardamos los datos de configuracion
     */
    public function saveSettingTemplate(Request $request)
    {
        try {
            $template = $request->input('template');
            $env = [
                'ENV_ENDPOINT' => $request->input('apiSrc'),
                'ENV_KEY' => $request->input('apiKey'),
                'ENV_STRIPE_PK' => $request->input('stripePk'),
                'ENV_GOOGLE_ID' => $request->input('googleId'),
                'ENV_FB_ID' => $request->input('facebookId'),
                'ENV_COUNTRY' => $request->input('country'),
            ];
            $pathUnzip = $this->downloadTemplate($template);
            $files = scandir($pathUnzip, 1);
            foreach ($files as $file) {
                if (strpos($file, '.js') !== false) {
                    $this->findAndReplace($pathUnzip . '/' . $file, $env);
                }
            }
            $this->makeZip();
            unlink(public_path() . '/' . $template);

            return view('vendor.installer.template');

        } catch (\Exception $e) {
            dd($e->getMessage());
            return $e->getMessage();
        }

    }

    /**
     * Creamos ZIP del template ya con las variables
     */
    private function makeZip()
    {
        try {
            $pathRawTemplate = public_path() . '/template-raw';
            $zipper = new \Madnest\Madzipper\Madzipper;
            $files = glob($pathRawTemplate);
            $zipper->make('awesome-website.zip')->add($files)->close();
            $this->deleteDir($pathRawTemplate);
        } catch (\Exception $e) {
            dd($e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Buscar y remplazar las variables
     */
    private function findAndReplace($file, $env = [])
    {
        try {
            $str = file_get_contents($file);
            foreach ($env as $key => $value) {
                $str = str_replace($key, $value, $str);
            }
            file_put_contents($file, $str);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Descargar template ZIP desde fuente
     */
    private function downloadTemplate($filename = '')
    {
        try {
            $zipper = new \Madnest\Madzipper\Madzipper;

            $url = self::$SOURCE_TEMPLATE . '/' . $filename;
            $filename = basename(self::$SOURCE_TEMPLATE . '/' . $filename);
            if (file_put_contents($filename, file_get_contents($url))) {
                $pathZip = public_path() . '/' . $filename;
                $zipper->make($pathZip)->folder('dist')->extractTo('template-raw');
                return public_path() . '/template-raw';
            } else {
                return null;
            }

        } catch (\Exception $e) {
            dd($e->getMessage());
            return $e->getMessage();
        }
    }

    public static function deleteDir($dirPath)
    {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }
}
