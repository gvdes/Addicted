<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProvidersController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function replyProvider(Request $request){//recibe los datos de la api Diller alojada en servidor Cedis 
        $created = [];//contenedor de creados
        $fail = [];//contendero de fallidos
        $pro = $request->provider;//se obtienen los proveedores
        foreach($pro as $provider){//se hace un foreach de los datos recibidos
        if($provider['CODPRO'] >= 800){//se verifica que el proveedor sea mayor o igual a 800 ya que solo recibira esos provedores por cuestion contable
        $delete = "DELETE FROM F_PRO WHERE CODPRO = ?";//se eliminan los proveedores existentes con el codigo recibido
        $exec = $this->conn->prepare($delete);
        $exec ->execute([$provider["CODPRO"]]);//se manda el codigo de el proveedor
        $insert = "INSERT INTO F_PRO (CODPRO,CCOPRO,NOFPRO,NOCPRO,DOMPRO,POBPRO,CPOPRO,PROPRO,TELPRO,FALPRO,DOCPRO,IFIPRO,FUMPRO) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";//se prepara el query para insertar los proveedores recibidos
        $exec = $this->conn->prepare($insert);
        $exec ->execute([//se insertan los datos recibidos
            $provider["CODPRO"],
            $provider["CCOPRO"],
            $provider["NOFPRO"],
            $provider["NOCPRO"],
            $provider["DOMPRO"],
            $provider["POBPRO"],
            $provider["CPOPRO"],
            $provider["PROPRO"],
            $provider["TELPRO"],
            $provider["FALPRO"],
            $provider["DOCPRO"],
            $provider["IFIPRO"],
            $provider["FUMPRO"]
        ]);
        $created[] = "El provedor ".$provider['CODPRO']." de Nombre ".$provider['NOFPRO']." fue creado con exito";//se guarda en conteedor de creados 

        }else{ $fail[]= "El proveedor ".$provider['NOFPRO']." no se creo correctamente debido a que solo se admiten proveedores mayor a 800";}// en caso de no ser >= 800 se almacena en fallidos
        }
        $res =[//se crea respuesta
            "creado"=>$created,
            "fallido"=>$fail
        ];
        return response()->json($res,200);//se retorna respuesta
    }
}
