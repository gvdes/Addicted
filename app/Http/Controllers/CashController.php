<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;

class CashController extends Controller
{
    public function __construct(){
        $rout = env("ACCESS");
        $ipserv = getHostByName(getHostName());
        $this->wrk = Store::where('local_domain',$ipserv)->first();
        $this->access = $rout.'\\'.$this->wrk->access_file.'.accdb';//conexion a access de sucursal
        if(file_exists($this->access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$this->access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die($this->access." no es un origen de datos valido."); }
    }

    public function OpenCash(Request $request){
        $cash = "SELECT * FROM T_TER WHERE CODTER = ?";
        $exec = $this->conn->prepare($cash);
        $exec->execute([$request->_cash]);
        $casher = $exec->fetch(\PDO::FETCH_ASSOC);
        if($casher){
            $update = "UPDATE T_TER SET ESTTER = ?, SINTER = ?, FECTER = DATE(), HOATER = TIME() WHERE CODTER = ? ";
            $exec = $this->conn->prepare($update);
            $res = $exec->execute([1,$request->initial_cash,$request->_cash]);
            if($res == 1){
                $response = [
                    "mssg"=>'Apertura de caja realizada',
                    "apertura"=>true,
                    "idtpv"=>str_pad($casher['CODTER'], 4, "0", STR_PAD_LEFT)."00".date('ymd'),
                    "fechas"=>date('Y/m/d H:m:s')
                ];
                return $response;
            }else{
                $response = [
                    "mssg"=>'No se pudo abrir la caja',
                    "apertura"=>false,
                    "idtpv"=>null,
                    "fechas"=>null
                ];
                return $response;
            }
            return $res;
        }else{
            $response = [
                "mssg"=>'No se encuentra la caja',
                "apertura"=>false,
                "idtpv"=>null,
                "fechas"=>null
            ];
            return $response;
        }
    }

}
