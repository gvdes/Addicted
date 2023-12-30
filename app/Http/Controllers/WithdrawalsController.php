<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalsController extends Controller
{
    public function __construct(){
    $access = env("ACCESS");//conexion a access de sucursal
    if(file_exists($access)){
    try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        }catch(\PDOException $e){ die($e->getMessage()); }
    }else{ die("$access no es un origen de datos valido."); }
    }

    public function replyWitdrawal(){
        $workpoint = env('WKP');
        $date = now()->format("Y-m-d");

        $rday = DB::table('withdrawals')->whereDate('created_at',$date)->where('_store',$workpoint)->get();

        if(count($rday) == 0){
            $with = "SELECT CODRET AS CODE, CAJRET AS TERMI, CONRET AS DESCRIP, IMPRET AS IMPORTE, PRORET AS PROVI, IIF( HORRET = '', FORMAT(FECRET,'YYYY-mm-dd')&' '&'00:00:00' ,FORMAT(FECRET,'YYYY-mm-dd')&' '&FORMAT(HORRET,'HH:mm:ss')) AS CREACION FROM F_RET WHERE FECRET = DATE()";
            $exec = $this->conn->prepare($with);
            $exec -> execute();
            $ret=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($ret){
            $colsTab = array_keys($ret[0]);
            foreach($ret as $with){
                $cash = DB::table('cash_registers')->where('terminal',$with['TERMI'])->value('id');
                $provider = DB::table('providers')->where('fs_id',$with['PROVI'])->value('id');
                $insret  = [
                    "code"=>$with['CODE'],
                    "_store"=>intval($workpoint),
                    "_cash"=>$cash,
                    "description"=>$with['DESCRIP'],
                    "import"=>$with['IMPORTE'],
                    "created_at"=>$with['CREACION'],
                    "updated_at"=>now(),
                    "_provider"=>$provider
                ];
                $insert = DB::table('withdrawals')->insert($insret);  
            }

            return response()->json($insret);
            }else{return response()->json("No hay retiradas para replicar");}
        }else{
            foreach($rday as $rms){
                $code[] = $rms->code;
            }
            $withn = "SELECT CODRET AS CODE, CAJRET AS TERMI, CONRET AS DESCRIP, IMPRET AS IMPORTE, PRORET AS PROVI, IIF( HORRET = '', FORMAT(FECRET,'YYYY-mm-dd')&' '&'00:00:00' ,FORMAT(FECRET,'YYYY-mm-dd')&' '&FORMAT(HORRET,'HH:mm:ss')) AS CREACION FROM F_RET WHERE FECRET = DATE() AND CODRET NOT IN (".implode(",",$code).")";
            $exec = $this->conn->prepare($withn);
            $exec -> execute();
            $retn=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($retn){
            $colsTab = array_keys($retn[0]);
            foreach($retn as $withn){
                $cash = DB::table('cash_registers')->where('terminal',$withn['TERMI'])->value('id');
                $provider = DB::table('providers')->where('fs_id',$withn['PROVI'])->value('id');
                $insret  = [
                    "code"=>$withn['CODE'],
                    "_store"=>intval($workpoint),
                    "_cash"=>$cash,
                    "description"=>$withn['DESCRIP'],
                    "import"=>$withn['IMPORTE'],
                    "created_at"=>$withn['CREACION'],
                    "updated_at"=>now(),
                    "_provider"=>$provider
                ];
                $insert = DB::table('withdrawals')->insert($insret);  
            }

            return response()->json($insret);
            }else{return response()->json("No hay retiradas para replicar");}
        }
    }
}
