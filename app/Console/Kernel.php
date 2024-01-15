<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StocksController;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        // $schedule->call(function (){
        //     $salesController = new SalesController();
        //     $salesController->replySales();
        // })->everyMinute()->between('8:00','22:00')->name('Replicador de ventas');

        $schedule->call(function (){
            $salesController = new StocksController();
            $salesController->replyStock();
        })->everyMinute()->between('8:00','22:00')->name('Replicador de stock');

        // $schedule->call(function (){//replicador de retiradas de la sucursal
        //     $workpoint = env('WKP');
        //     $date = now()->format("Y-m-d");

        //     $rday = DB::table('withdrawals')->whereDate('created_at',$date)->where('_store',$workpoint)->get();

        //     if(count($rday) == 0){
        //         $with = "SELECT CODRET AS CODE, CAJRET AS TERMI, CONRET AS DESCRIP, IMPRET AS IMPORTE, PRORET AS PROVI, IIF( HORRET = '', FORMAT(FECRET,'YYYY-mm-dd')&' '&'00:00:00' ,FORMAT(FECRET,'YYYY-mm-dd')&' '&FORMAT(HORRET,'HH:mm:ss')) AS CREACION FROM F_RET WHERE FECRET = DATE()";
        //         $exec = $this->conn->prepare($with);
        //         $exec -> execute();
        //         $ret=$exec->fetchall(\PDO::FETCH_ASSOC);
        //         if($ret){
        //         $colsTab = array_keys($ret[0]);
        //         foreach($ret as $with){
        //             $cash = DB::table('cash_registers')->where('terminal',$with['TERMI'])->value('id');
        //             $provider = DB::table('providers')->where('fs_id',$with['PROVI'])->value('id');
        //             $insret  = [
        //                 "code"=>$with['CODE'],
        //                 "_store"=>intval($workpoint),
        //                 "_cash"=>$cash,
        //                 "description"=>$with['DESCRIP'],
        //                 "import"=>$with['IMPORTE'],
        //                 "created_at"=>$with['CREACION'],
        //                 "updated_at"=>now(),
        //                 "_provider"=>$provider
        //             ];
        //             $insert = DB::table('withdrawals')->insert($insret);
        //         }

        //         return response()->json($insret);
        //         }else{return response()->json("No hay retiradas para replicar");}
        //     }else{
        //         foreach($rday as $rms){
        //             $code[] = $rms->code;
        //         }
        //         $withn = "SELECT CODRET AS CODE, CAJRET AS TERMI, CONRET AS DESCRIP, IMPRET AS IMPORTE, PRORET AS PROVI, IIF( HORRET = '', FORMAT(FECRET,'YYYY-mm-dd')&' '&'00:00:00' ,FORMAT(FECRET,'YYYY-mm-dd')&' '&FORMAT(HORRET,'HH:mm:ss')) AS CREACION FROM F_RET WHERE FECRET = DATE() AND CODRET NOT IN (".implode(",",$code).")";
        //         $exec = $this->conn->prepare($withn);
        //         $exec -> execute();
        //         $retn=$exec->fetchall(\PDO::FETCH_ASSOC);
        //         if($retn){
        //         $colsTab = array_keys($retn[0]);
        //         foreach($retn as $withn){
        //             $cash = DB::table('cash_registers')->where('terminal',$withn['TERMI'])->value('id');
        //             $provider = DB::table('providers')->where('fs_id',$withn['PROVI'])->value('id');
        //             $insret  = [
        //                 "code"=>$withn['CODE'],
        //                 "_store"=>intval($workpoint),
        //                 "_cash"=>$cash,
        //                 "description"=>$withn['DESCRIP'],
        //                 "import"=>$withn['IMPORTE'],
        //                 "created_at"=>$withn['CREACION'],
        //                 "updated_at"=>now(),
        //                 "_provider"=>$provider
        //             ];
        //             $insert = DB::table('withdrawals')->insert($insret);
        //         }

        //         return response()->json($insret);
        //         }else{return response()->json("No hay retiradas para replicar");}
        //     }
        // })->everyThirtyMinutes()->between('8:00','22:00');

        // $schedule->call(function (){//replicador de el reloj checador
        //     $zkteco = env('ZKTECO');
        //     $zk = new ZKTeco($zkteco);
        //     if($zk->connect()){
        //         $assists = $zk->getAttendance();
        //         if($assists){
        //             foreach($assists as $assist){
        //                 $serie = ltrim(stristr($zk->serialNumber(),'='),'=');
        //                 $sucursal = DB::table('assist_devices')->where('serial_number',$serie)->first();
        //                 $user = DB::table('users')->where('RC_id',intval($assist['id']))->value('id');
        //                 $report = [
        //                 "auid" => $assist['uid'],//id checada checador
        //                 "register" => $assist['timestamp'], //horario
        //                 "_user" => $user,//id del usuario
        //                 "_store"=> $sucursal->_store,
        //                 "_type"=>$assist['type'],//entrada y salida
        //                 "_class"=>$assist['state'],
        //                 "_device"=>$sucursal->id,
        //                 ];
        //                 $insert = DB::table('assists')->insert($report);
        //             }
        //             $replicadas = count($assists);
        //             $zk -> clearAttendance();
        //             echo "se replicaron ".$replicadas." asistencias";
        //         }else{echo "No hay registros por el momento";}
        //     }else{echo "No hay conexion a el checador",501;}
        // })->everyFiveMinutes()->between('8:00','22:00');

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
