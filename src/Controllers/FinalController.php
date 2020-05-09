<?php

namespace RachidLaasri\LaravelInstaller\Controllers;

use Illuminate\Routing\Controller;
use RachidLaasri\LaravelInstaller\Events\LaravelInstallerFinished;
use RachidLaasri\LaravelInstaller\Helpers\EnvironmentManager;
use RachidLaasri\LaravelInstaller\Helpers\FinalInstallManager;
use RachidLaasri\LaravelInstaller\Helpers\InstalledFileManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinalController extends Controller
{
    static $CLIENT_DESCRIPTION = 'Token generado automaticamente desde el instalador';
    /**
     * Generamos token para conexión con el cliente (FRONT)
     */
    private function generateTokenClient(){
        $token = Str::random(45);
        try{
            $values = array(
                'token' => 'ec_'.$token,
                'description' => self::$CLIENT_DESCRIPTION,
                'created_at' =>  date("Y-m-d h:m:s")
            );
            DB::table('client_tokens')
            ->insert($values);
            return $values;
        }catch(\Execption $e){
            return $e->getMessage();
        }
    }

    /**
     * Metodo publico con el objetivo de extrar la data de la creación del token
     */

     public function getTokenClient()
     {
         try{
            $data = DB::table('client_tokens')->first();
            return $data;
         }catch(\Execption $e){
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
    public function connectionData(){
        $data = $this->getTokenClient();
        return view('vendor.installer.final', compact('data'));
    }
}
