<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rats\Zkteco\Lib\ZKTeco;

class AssistController extends Controller
{
    public function replyAssist(){
        $zkteco = env('ZKTECO');
        $zk = new ZKTeco($zkteco);
        if($zk->connect()){
            $assists = $zk->getAttendance();
            if($assists){
                foreach($assists as $assist){
                    $serie = ltrim(stristr($zk->serialNumber(),'='),'=');
                    $sucursal = DB::table('assist_devices')->where('serial_number',$serie)->first();
                    $user = DB::table('users')->where('RC_id',intval($assist['id']))->value('id');
                    $report = [
                    "auid" => $assist['uid'],//id checada checador
                    "register" => $assist['timestamp'], //horario
                    "_user" => $user,//id del usuario
                    "_store"=> $sucursal->_store,
                    "_type"=>$assist['type'],//entrada y salida
                    "_class"=>$assist['state'],
                    "_device"=>$sucursal->id,
                    ];
                    $insert = DB::table('assists')->insert($report);
                }
                $zk -> clearAttendance();
                return response()->json($report,201);
            }else{return response()->json("No hay registros por el momento",404);}
        }else{return response()->json("No hay conexion a el checador",501);}

    }
}
