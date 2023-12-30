<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClientsController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function replyClients(Request $request){//recibe los datos de la api Diller alojada en servidor Cedis 
        $created = [];//contenedor de creados
        $fail = [];//contendero de fallidos
        $cli = $request->client;//se obtienen los clientes
        foreach($cli as $client){//se hace un foreach de los datos recibidos
        $delete = "DELETE FROM F_CLI WHERE CODCLI = ?";//se eliminan los clientes existentes con el codigo recibido
        $exec = $this->conn->prepare($delete);
        $exec ->execute([$client["CODCLI"]]);//se manda el codigo de el clientes   
        $insert = "INSERT INTO F_CLI (CODCLI,CCOCLI,NIFCLI,NOFCLI,NOCCLI,DOMCLI,POBCLI,CPOCLI,PROCLI,TELCLI,AGECLI,FPACLI,TARCLI,TCLCLI,FALCLI,NVCCLI,DOCCLI,IFICLI,FUMCLI) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";//se prepara el query para insertar los proveedores recibidos
        $exec = $this->conn->prepare($insert);
        $exec ->execute([//se insertan los datos recibidos
            $client["CODCLI"],
            $client["CCOCLI"],
            $client["NIFCLI"],
            $client["NOFCLI"],
            $client["NOCCLI"],
            $client["DOMCLI"],
            $client["POBCLI"],
            $client["CPOCLI"],
            $client["PROCLI"],
            $client["TELCLI"],
            $client["AGECLI"],
            $client["FPACLI"],
            $client["TARCLI"],
            $client["TCLCLI"],
            $client["FALCLI"],
            $client["NVCCLI"],
            $client["DOCCLI"],
            $client["IFICLI"],
            $client["FUMCLI"]     
        ]);
        $created[] = "El cliente ".$client['CODCLI']." de Nombre ".$client['NOFCLI']." fue creado con exito";//se guarda en conteedor de creados 
        }
        $res =[//se crea respuesta
            "creado"=>$created,
            "fallido"=>$fail
        ];
        return response()->json($res,200);//se retorna respuesta
    }

    public function conditionSpecial(Request $request){
        $fil = $request->special;
        $client = $request->client;
        $clientms =  "DELETE FROM F_PRC WHERE CLIPRC = $client";
        $exec = $this->conn->prepare($clientms);
        $exec -> execute();
            foreach($fil as $con){
                $clsd =[
                    $con['CLIPRC'],
                    $con['ARTPRC'],
                    $con['PREPRC'],
                    $con['TIPPRC'],
                    $con['AOFPRC'],
                ];
            $inset="INSERT INTO F_PRC (CLIPRC,ARTPRC,PREPRC,TIPPRC,AOFPRC) VALUES(?,?,?,?,?)";
            $exec = $this->conn->prepare($inset);
            $exec -> execute($clsd);


        }
        return response()->json("Generado Correctamente");
    }

    public function refreshLoyaltyCard(Request $request){
        $tar = $request->tarjetas;
        $del = "DELETE FROM T_TFI";
        $exec = $this->conn->prepare($del);
        $exec -> execute();
        foreach($tar as $taget){
            $ins = [
                $taget['CODTFI'],
                $taget['TTFTFI'],
                $taget['IDETFI'],
                $taget['CLITFI'],
                $taget['FALTFI'],
                $taget['FVATFI'],
                $taget['TOTTFI']
            ];
            $insert = "INSERT INTO T_TFI (CODTFI,TTFTFI,IDETFI,CLITFI,FALTFI,FVATFI,TOTTFI) VALUES (?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($insert);
            $exec -> execute($ins);
        }
        return response()->json("Proceso Realizado");
    }
}
