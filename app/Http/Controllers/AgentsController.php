<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentsController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->con  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }

        $access = env("GENERAL");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }
   

    public function index(Request $request){
        $depen = [];
        $agen = [];
        $agentes = $request->agentes;
        foreach($agentes as $agente){
            $delagen = "DELETE FROM F_AGE WHERE CODAGE = ?";
            $exec = $this->conn->prepare($delagen);
            $exec -> execute([$agente['CODAGE']]);
            $prepare = [
                $agente['CODAGE'],
                $agente['TEMAGE'],
                $agente['ZONAGE'],
                $agente['IMPAGE'],
                $agente['COMAGE'],
                $agente['TCOAGE'],
                $agente['IVAAGE'],
                $agente['IRPAGE'],
                $agente['PIRAGE'],
                $agente['FALAGE'],
                $agente['FAXAGE'],
                $agente['EMAAGE'],
                $agente['WEBAGE'],
                $agente['PAIAGE'],
                $agente['PCOAGE'],
                $agente['TEPAGE'],
                $agente['CLAAGE'],
                $agente['DNIAGE'],
                $agente['RUTAGE'],
                $agente['CUWAGE'],
                $agente['CAWAGE'],
                $agente['SUWAGE'],
                $agente['MEWAGE'],
                $agente['CPOAGE'],
                $agente['PROAGE'],
                $agente['ENTAGE'],
                $agente['OFIAGE'],
                $agente['DCOAGE'],
                $agente['CUEAGE'],
                $agente['BANAGE'],
                $agente['LISAGE'],
                $agente['CONAGE'],
                $agente['DOMAGE'],
                $agente['NOMAGE'],
                $agente['NOCAGE'],
                $agente['MEMAGE'],
                $agente['OBSAGE'],
                $agente['FORAGE'],
                $agente['LFOAGE'],
                $agente['FFOAGE'],
                $agente['OFOAGE'],
                $agente['UREAGE'],
                $agente['CURAGE'],
                $agente['URLAGE'],
                $agente['CATAGE'],
                $agente['FCCAGE'],
                $agente['FFCAGE'],
                $agente['PUNAGE'],
                $agente['CVEAGE'],
                $agente['CREAGE'],
                $agente['PURAGE'],
                $agente['JEQAGE'],
                $agente['CSAAGE'],
                $agente['AGJAGE'],
                $agente['DMWAGE'],
                $agente['FOTAGE'],
                $agente['POBAGE'],
                $agente['CTPAGE']
            ];
            $inser = "INSERT INTO F_AGE VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($inser);
            $exec -> execute($prepare);
            $agen[]="El agente ".$agente['CODAGE']." ".$agente['NOMAGE']." se inserto correctamente";
        }
        
    

        $dependientes = $request->dependientes;
        foreach ($dependientes as $dependiente){
            $deldep = "DELETE FROM T_DEP WHERE CODDEP = ?";
            $exec = $this->conn->prepare($deldep);
            $exec -> execute([$dependiente['CODDEP']]);
            $depre = [
                $dependiente['CODDEP'],
                $dependiente['NOMDEP'],
                $dependiente['PERDEP'],
                $dependiente['IMADEP'],
                $dependiente['CLADEP'],
                $dependiente['CCLDEP'],
                $dependiente['ESTDEP'],
                $dependiente['AGEDEP'],
                $dependiente['IDIDEP']
            ];
            $insertdep = "INSERT INTO T_DEP VALUES (?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($insertdep);
            $exec -> execute($depre);
            $depen[]="El dependiente ".$dependiente['CODDEP']." ".$dependiente['NOMDEP']." se inserto correctamente";

        }

        

        $res = [
            "dependientes"=>$depen,
            "agentes"=>$agen
        ];

        return response()->json($res);
    }

    public function replyUser(Request $request){
        $workpoint = env('WKP');
        // return $workpoint;
        $access = DB::table('stores')->where('id',$workpoint)->value('access_file');

        $usu = $request->usuario;

        $usuario = [
            $usu['CODUSU'],
            $usu['NOMUSU'],
            $usu['CLAUSU'],
            'FS'.$access,
            $usu['GESUSU'],
            $usu['CONUSU'],
            $usu['LABUSU'],
            $usu['ALMARTUSU'],
            $usu['APPUSU'],
            $usu['ALBUSU'],
            $usu['FACUSU'],
            $usu['PREUSU'],
            $usu['PPRUSU'],
            $usu['FREUSU'],
            $usu['PCLUSU'],
            $usu['RECUSU'],
            $usu['ENTUSU'],
            $usu['FABUSU'],
            $usu['FRDUSU'],
            $usu['IDIUSU'],
            $usu['ELIUSU'],
        ];
      
        $insertusu = "INSERT INTO F_USU (CODUSU,NOMUSU,CLAUSU,EMPUSU,GESUSU,CONUSU,LABUSU,ALMARTUSU,APPUSU,ALBUSU,FACUSU,PREUSU,PPRUSU,FREUSU,PCLUSU,RECUSU,ENTUSU,FABUSU,FRDUSU,IDIUSU,ELIUSU) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";    
        $exec = $this->conn->prepare($insertusu);
        $exec -> execute($usuario);

        $permi = $request->permisos;
        foreach($permi as $permiso){
            $program = [
                $permiso['CODCFG'],
                $permiso['NUMCFG'],
                $permiso['TEXCFG'],
                $permiso['TIPCFG'],

            ];
            $insertpro = "INSERT INTO F_CFG (CODCFG,NUMCFG,TEXCFG,TIPCFG) VALUES (?,?,?,?)";
            $exec = $this->conn->prepare($insertpro);
            $exec -> execute($program);   
        }

        return response()->json("El usuario ".$usu['NOMUSU']." se inserto");

    }

    public function replyAgents(Request $request){
        $age = $request->agente;

        $agente = [
            $age['CODAGE'],
            $age['FALAGE'],
            $age['NOMAGE'],

        ];
        $insertage = "INSERT INTO F_AGE (CODAGE,FALAGE,NOMAGE) VALUES (?,?,?)";
        $exec = $this->con->prepare($insertage);
        $exec -> execute($agente);


        $dep = $request->dependiente;

        $depe = [
            $dep['CODDEP'],
            $dep['NOMDEP'],
            $dep['PERDEP'],
            $dep['CLADEP'],
            $dep['AGEDEP'],

        ];
    
        $insertdep = "INSERT INTO T_DEP (CODDEP,NOMDEP,PERDEP,CLADEP,AGEDEP) VALUES (?,?,?,?,?)";
        $exec = $this->con->prepare($insertdep);
        $exec -> execute($depe);
        return response()->json("El agente ".$dep['NOMDEP']." se inserto correctamente");
    }


}
