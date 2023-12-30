<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WarehousesController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function transferWarehouse(Request $request){//TRASPASOS ENTRE ALMACENES
        $id = $request->id;
        $transfer = DB::table('transfers_between_warehouses as tbw')->join('warehouses as WO','WO.id','tbw._origin_warehouse')->join('warehouses as WD','WD.id','tbw._destini_warehouse')->join('users as U','U.id','tbw._user')->where('tbw.id',$id)->select('WO.alias as origen','WD.alias as destino','tbw.notes as notas','U.name as usuario','tbw._state as state')->first();
        if($transfer){
            $status = $transfer->state;
            if($status == 3){
                $idmaxa = "SELECT MAX(DOCTRA) as CODIGO FROM F_TRA";
                $exec = $this->conn->prepare($idmaxa);
                $exec -> execute();
                $idmax=$exec->fetch(\PDO::FETCH_ASSOC);
                $transferfs = $idmax['CODIGO'] + 1; 
                $trans = [
                    $transferfs,
                    now()->format('Y-m-d'),
                    $transfer->origen,
                    $transfer->destino,
                    $transfer->notas." creado por ".$transfer->usuario
                ];
                $res = [
                    "traspaso"=>$transferfs,
                    "fecha"=>now()->format('Y-m-d'),
                    "almOrigen"=>$transfer->origen,
                    "almDestino"=>$transfer->destino,
                    "notas"=>$transfer->notas." creado por ".$transfer->usuario
                ];
                $headert = "INSERT INTO F_TRA (DOCTRA,FECTRA,AORTRA,ADETRA,COMTRA) VALUES (?,?,?,?,?)";
                $exec = $this->conn->prepare($headert);
                $exec -> execute($trans);
                $instra = DB::table('transfers_between_warehouses')->where('id',$id)->update(['code_fs'=>$transferfs]);

                $trabodies = DB::table('transfer_bw_bodies as TBB')->join('products as P','P.id','TBB._product')->where('TBB._transfer',$id)->select('P.code as codigo','TBB.amount')->get();
                $pos = 1;
                foreach($trabodies as $bod){
                    $traspb = [
                        $transferfs,
                        $pos,
                        $bod->codigo,
                        $bod->amount,
                        0            
                    ];
                    $bodies = "INSERT INTO F_LTR (DOCLTR,LINLTR,ARTLTR,CANLTR,BULLTR) VALUES (?,?,?,?,?)";
                    $exec = $this->conn->prepare($bodies);
                    $exec -> execute($traspb);
                    $pos++;
                    $upddestiny = "UPDATE F_STO SET ACTSTO = ACTSTO + $bod->amount, DISSTO = DISSTO + $bod->amount WHERE ARTSTO = ? AND ALMSTO = ? ";
                    $exec = $this->conn->prepare($upddestiny);
                    $exec -> execute([$bod->codigo,$transfer->destino]);
                    $updorigin = "UPDATE F_STO SET ACTSTO = ACTSTO - $bod->amount, DISSTO = DISSTO - $bod->amount WHERE ARTSTO = ? AND ALMSTO = ? ";
                    $exec = $this->conn->prepare($updorigin);
                    $exec -> execute([$bod->codigo,$transfer->origen]);
                    
                }
                return response()->json($res,201);  
            }else{return response()->json("El traspaso ".$id." aun no ha finalizado",400);}        
        }else{return response()->json("No se encuentra el traspaso",404);}

    }

    public function Consolidation(Request $request){//CONSOLIDACION DE ALMACEN
        $id = $request->id;
        $consolidation = DB::table('consolidations AS C')->join('warehouses as W','W.id','C._warehouse')->where('C.id',$id)->select('W.alias as almacen','C.*')->first();
        if($consolidation){
            if($consolidation->_state == 3){
                $bodies = DB::table('consolidation_bodies AS CB')->join('products AS P','P.id','CB._product')->where('CB._consolidation',$id)->select('P.code AS codigo','CB.before_count AS antes','CB.count AS contado')->get();
                foreach($bodies as $bodie){
                    $articulos [] = $bodie->codigo;
                    $ins = [
                        $consolidation->almacen,
                        now()->format('Y-m-d'),
                        $bodie->codigo,
                        $bodie->antes,
                        $bodie->contado,
                        $bodie->antes,
                        $bodie->contado,
                    ];
                    $insertfs = "INSERT INTO F_CIN (ALMCIN,FECCIN,ARTCIN,UACCIN,URECIN,DACCIN,DRECIN) VALUES (?,?,?,?,?,?,?)";
                    $exec = $this->conn->prepare($insertfs);
                    $exec -> execute($ins);

                    $upddestiny = "UPDATE F_STO SET ACTSTO = $bodie->contado, DISSTO = $bodie->contado WHERE ARTSTO = ? AND ALMSTO = ? ";
                    $exec = $this->conn->prepare($upddestiny);
                    $exec -> execute([$bodie->codigo,$consolidation->almacen]);
                }
                $res = [
                    "almacenConsolidado"=>$consolidation->almacen,
                    "articulosConsolidados"=>count($articulos)
                ];
                return response()->json($res,201);
            }else{return response()->json("La consolidacion requerida aun no ha finalizado",400);}
        }else{return response()->json("No se encuntra ninguan consolidacion con el id ".$id,404);}


    }
}
