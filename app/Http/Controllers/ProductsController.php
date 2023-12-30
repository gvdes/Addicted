<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function index(Request $request){//se envia articulos para su comparacion con cedis y mysql, aplica solo para sucursales con tipo de precio 2
        // $articulos=[];//contenedor de articulos
        $produ = $request->articulos;
        $proced = "SELECT CODART  FROM F_ART";//query para mostrar articulos
        $exec = $this->conn->prepare($proced);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);
        $cuantos = count($fil);
        foreach($fil as $row){//foreach de articulos
            $articulos[]=$row['CODART'];//se obtiene el codigo de los articulos concatenads con comilla simple para su procesamiento
        }
        $dife = array_diff($produ,$articulos);
        $diferencia = array_values($dife);
        $res = [
            "articulos"=>$cuantos,
            "faltantes"=>$diferencia
        ];

        return response()->json($res,200);
    }

    public function pairingProducts(Request $request){
        $fil = $request->pro;

        $psfs = "SELECT CODART FROM F_ART";
        $exec = $this->conn->prepare($psfs);
        $exec -> execute();
        $profs=$exec->fetchall(\PDO::FETCH_ASSOC);
        $colsTab = array_keys($profs[0]);//llaves de el arreglo 
        foreach($profs as $raw){
            foreach($colsTab as $col){ $raw[$col] = utf8_encode($raw[$col]); }
            $cods[]="'".$raw['CODART']."'";
        }
        $dife = array_diff($cods,$fil);
        $diferencia = array_values($dife);
        if(count($diferencia)){
        $deleteart = "DELETE FROM F_ART WHERE CODART  IN (".implode(",",$diferencia).")";
        $exec = $this->conn->prepare($deleteart);
        $exec -> execute();
        $deletetar = "DELETE FROM F_LTA WHERE ARTLTA  IN (".implode(",",$diferencia).")";
        $exec = $this->conn->prepare($deletetar);
        $exec -> execute();
        $deletesto = "DELETE FROM F_STO WHERE ARTSTO  IN (".implode(",",$diferencia).")";
        $exec = $this->conn->prepare($deletesto);
        $exec -> execute();
        }

        $proced = "SELECT CODART FROM F_ART";
        $exec = $this->conn->prepare($proced);
        $exec -> execute();
        $profsd=$exec->fetchall(\PDO::FETCH_ASSOC);
        $colsTab = array_keys($profsd[0]);//llaves de el arreglo 
        foreach($profsd as $row){
            foreach($colsTab as $col){ $row[$col] = utf8_encode($row[$col]); }
            $codigo[]="'".$row['CODART']."'";
        }
        $dife = array_diff($fil,$codigo);
        $faltantes = array_values($dife);

        $alm = "SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($alm);
        $exec -> execute();
        $rowalm=$exec->fetchall(\PDO::FETCH_ASSOC);

        $cedis = DB::table('stores')->where('_type',1)->where('id',1)->value('local_domain');
        $url = $cedis."/Diller/public/api/products/missing";
        $ch = curl_init($url);//inicio de curl
        $data = json_encode(["products" => $faltantes]);//se codifica el arreglo de los proveedores
        //inicio de opciones de curl
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        //fin de opciones e curl
        $exec = curl_exec($ch);//se executa el curl
        $exc = json_decode($exec,true);//se decodifican los datos decodificados
        curl_close($ch);//cirre de curl
            if($exc == null){
                return response()->json("LOS ARTICULOS ESTAN BIEN");
            }else{
                $arch = $exc['articulos'];
                foreach($arch as $pro){
                    $res[] = "articulo ".$pro['CODART']." insertado con exito";
                    $ins[] = $pro['CODART'];
                    $rows = array_values($pro);
                    $insert = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                    $exec = $this->conn->prepare($insert);
                    $exec -> execute($rows);
                }

                $prec = $exc['precios'];
                foreach($prec as $prices){
                    $pri = array_values($prices);
                    $insert = "INSERT INTO F_LTA (TARLTA, ARTLTA , MARLTA , PRELTA) VALUES (?,?,?,?)";
                    $exec = $this->conn->prepare($insert);
                    $exec -> execute($pri);
                }
            
                $query = "INSERT INTO F_STO(ARTSTO, ALMSTO, MINSTO, MAXSTO, ACTSTO, DISSTO) VALUES(?,?,?,?,?,?)";
                $exec = $this->conn->prepare($query);
                foreach($rowalm as $alma){
                    $almacen = $alma['CODALM'];
                    foreach($ins as $artins){    
                        try{
                        $exec->execute([$artins, $almacen, 0, 0, 0, 0]);
                        }catch (\PDOException $e){ die($e->getMessage());}
                    }
                }
                return response()->json($res);
            }
    }

    public function replaceProducts(Request $request){
        $factusol=[];
        $products = $request->product;
        foreach($products as $product){
            $original = "'".$product['original']."'";
            $replace = "'".$product['replace']."'";
            try{
            $upda = "UPDATE F_LFA SET ARTLFA = $replace WHERE ARTLFA = $original";
            $exec = $this->conn->prepare($upda);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en facturas por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en facturas";}
            $updsto = "UPDATE F_LFR SET ARTLFR = $replace WHERE ARTLFR = $original";
            $exec = $this->conn->prepare($updsto);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en facturas recibidas por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en facturas recibidas";}
            $updlta = "UPDATE F_LEN SET ARTLEN = $replace WHERE ARTLEN = $original";
            $exec = $this->conn->prepare($updlta);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en entradas por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en entradas";}
            $updltr = "UPDATE F_LTR SET ARTLTR = $replace WHERE ARTLTR = $original";
            $exec = $this->conn->prepare($updltr);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en traspasos por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en traspasos";}
            $updcin = "UPDATE F_LFB SET ARTLFB = $replace WHERE ARTLFB = $original";
            $exec = $this->conn->prepare($updcin);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en abonos por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en abonos";}
            $upddev = "UPDATE F_LFD SET ARTLFD = $replace WHERE ARTLFD = $original";
            $exec = $this->conn->prepare($upddev);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en devoluciones por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en devoluciones";}
            $deleteart = "DELETE FROM F_ART WHERE CODART = $original";
            $exec = $this->conn->prepare($deleteart);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Articulos";}else{$factusol[]=$product['original']." error sl eliminar en Articulos";}
            $deletetar = "DELETE FROM F_LTA WHERE ARTLTA = $original";
            $exec = $this->conn->prepare($deletetar);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Precios";}else{$factusol[]=$product['original']." error sl eliminar en Precios";}
            $deletesto = "DELETE FROM F_STO WHERE ARTSTO = $original";
            $exec = $this->conn->prepare($deletesto);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Stock";}else{$factusol[]=$product['original']." error sl eliminar en Stock";}
            $deleteean = "DELETE FROM F_EAN WHERE ARTEAN = $original";
            $exec = $this->conn->prepare($deleteean);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Familiarizados";}else{$factusol[]=$product['original']." error sl eliminar en Familiarizados";}
            }catch (\PDOException $e){ die($e->getMessage());}
        }
        return response()->json($factusol);

      
    }
    
    public function highProducts(Request $request){
        $insertados=[];
        $actualizados=[];
        $fail=[
            "categoria"=>[],
            "codigo_barras"=>[],
            "codigo_corto"=>[], 
        ];
        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);

        $tari ="SELECT CODTAR FROM F_TAR";
        $exec = $this->conn->prepare($tari);
        $exec -> execute();
        $filtar=$exec->fetchall(\PDO::FETCH_ASSOC);

        $articulos= $request->product;
        
        foreach($articulos as $art){
            $codigo = trim($art["CODIGO"]);
            $deslarga = trim($art["DESCRIPCION"]);
            $desgen = trim(substr($art["DESCRIPCION"],0,50));
            $deset = trim(substr($art["DESCRIPCION"],0,30));
            $destic = trim(substr($art["DESCRIPCION"],0,20));
            $famart = trim($art["FAMILIA"]);
            $cat = trim($art["CATEGORIA"]);
            $date_format = date("d/m/Y");
            // $barcode = trim($art["CB"]);
            if(isset($art["CB"])){$barcode = trim($art["CB"]);}else{$barcode = null;}
            // $cost = $art["COSTO"];
            if(isset($art["COSTO"])){$cost = $art["COSTO"];}else{$cost = 0;}
            // $medidas = trim($art["MEDIDAS NAV"]);
            if(isset($art["MEDIDAS NAV"])){$medidas = trim($art["MEDIDAS NAV"]);}else{$medidas = null;}
            // $luces = trim($art["#LUCES"]);
            if(isset($art["#LUCES"])){$luces = trim($art["#LUCES"]);}else{$luces = null;}
            $PXC = trim($art["PXC"]);
            $refart = trim($art["REFERENCIA"]);
            $cp3art = trim($art["UNIDA MED COMPRA"]);

            $codbar = $barcode == null ? "'"."'" : $barcode;
            $luz = $luces == null ? "'"."'" : $luces;
            $med = $medidas == null ? "'"."'" : $medidas;

            $articulo  = [              
                $codigo,
                $codbar,
                $famart,
                $desgen,
                $deset,
                $destic,
                $deslarga,
                $art["PXC"],
                $art["CODIGO CORTO"],
                $art["PROVEEDOR"],
                $refart,
                $art["FABRICANTE"],
                $cost,
                $date_format,
                $date_format,
                $art["PXC"],
                1,
                1,
                1,
                $cat,
                $luz,
                $cp3art,
                $art["PRO RES"],
                $med,
                0,
                "Peso"
            ];



            $caty = DB::table('product_categories as PC')->join('product_categories as PF', 'PF.id', '=','PC.root')->where('PC.alias', $cat)->where('PF.alias', $famart)->value('PC.id');
            if($caty){
                $sqlart = "SELECT CODART, EANART FROM F_ART WHERE CODART = ?";
                $exec = $this->conn->prepare($sqlart);
                $exec -> execute([$codigo]);
                $arti=$exec->fetch(\PDO::FETCH_ASSOC);
     
                if($arti){
                    $update = "UPDATE F_ART SET FAMART = "."'".$famart."'"." , CP1ART = "."'".$cat."'"."  , FUMART = "."'".$date_format."'".", EANART = ".$codbar.", PCOART = ".$cost.", UPPART = ".$PXC." , EQUART = ".$PXC.", REFART = "."'".$refart."'"."  , CP3ART = "."'".$cp3art."'"."  WHERE CODART = ? "; 
                    $exec = $this->conn->prepare($update);
                    $exec -> execute([$codigo]);
                    $actualizados[]="Se actualizo el modelo  ".$codigo." con codigo de barras ".$barcode;
                }else{
                    if($barcode != null){
                    $codigob = "SELECT CODART, EANART FROM F_ART WHERE EANART = "."'".$barcode."'";
                    $exec = $this->conn->prepare($codigob);
                    $exec -> execute();
                    $barras=$exec->fetch(\PDO::FETCH_ASSOC);
                    if($barras){$fail['codigo_barras'][]="El codigo de barras ".$barcode." esta otorgado a el articulo ".$barras['CODART']." no se pueden duplicar";}
                    }
                        
                        $codigoc = "SELECT CODART, CCOART FROM F_ART WHERE CCOART = ".$art["CODIGO CORTO"];
                        $exec = $this->conn->prepare($codigoc);
                        $exec -> execute();
                        $corto=$exec->fetch(\PDO::FETCH_ASSOC);
                    
                        if($corto){$fail['codigo_corto'][]="El codigo corto ".$art["CODIGO CORTO"]." esta otorgado al articulo ".$corto['CODART']." no se pueden duplicar";
                        }else{
                            $insert = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                            $exec = $this->conn->prepare($insert);
                            $exec -> execute($articulo);
                            foreach($fil as $row){
                                $alm=$row['CODALM'];
                                $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                                $exec = $this->conn->prepare($insertsto);
                                $exec -> execute([$codigo,$alm,0,0,0,0]);
                            }
                            foreach($filtar as $tar){
                                $price =$tar['CODTAR'];
                                $insertlta = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?) ";
                                $exec = $this->conn->prepare($insertlta);
                                $exec -> execute([$price,$codigo,0,0]);
                            }
                            $insertados[]="Se inserto el codigo ".$codigo."con exito";
                        }
                } 
            }else{$fail['categoria'][]="no existe la categoria ".$cat." de la familia ".$famart." de el producto ".$codigo;}    
        }
        $res = [
            "insertados"=>$insertados,
            "acutalizados"=>$actualizados,
            "fail"=>$fail
        ];
        return response()->json($res);

    }

    public function highPrices(Request $request){
        $priceslocal = $request->prices;
        $goals=[];
        $fails=[];
        $date_format = date("d/m/Y");
        foreach($priceslocal as $price){


            
            $centro = "UPDATE F_LTA SET PRELTA = ". $price['CENTRO']." WHERE ARTLTA = ? AND TARLTA = 6";
            $exec = $this->conn->prepare($centro);
            $exec -> execute([$price["MODELO"]]);
            
           
            $especial = "UPDATE F_LTA SET PRELTA = ". $price['ESPECIAL']." WHERE ARTLTA = ? AND TARLTA = 5";
            $exec = $this->conn->prepare($especial);
            $exec -> execute([$price["MODELO"]]);
           
           
            $caja = "UPDATE F_LTA SET PRELTA = ". $price['CAJA']." WHERE ARTLTA = ? AND TARLTA = 4";
            $exec = $this->conn->prepare($caja);
            $exec -> execute([$price["MODELO"]]);
           
            
            $docena = "UPDATE F_LTA SET PRELTA = ". $price['DOCENA']." WHERE ARTLTA = ? AND TARLTA = 3";
            $exec = $this->conn->prepare($docena);
            $exec -> execute([$price["MODELO"]]);
            
            
            $mayoreo = "UPDATE F_LTA SET PRELTA = ". $price['MAYOREO']." WHERE ARTLTA = ? AND TARLTA = 2 ";
            $exec = $this->conn->prepare($mayoreo);
            $exec -> execute([$price["MODELO"]]);
           
            
            $menudeo = "UPDATE F_LTA SET PRELTA = ". $price['MENUDEO']." WHERE ARTLTA = ? AND TARLTA = 1";
            $exec = $this->conn->prepare($menudeo);
            $exec -> execute([$price["MODELO"]]);
           
            
            $costo = "UPDATE F_ART SET PCOART = ". $price['AAA']." , FUMART = ".$date_format." WHERE CODART = ? ";
            $exec = $this->conn->prepare($costo);
            $exec -> execute([$price["MODELO"]]);
            if($exec){$goals['factusol'][]=$price['MODELO']." Precios Modificados factusol";}else{$fails['factusol']= $price['MODELO']." error al actualizar factusol";}
            

        
        }
        $res = [
            "goals"=>$goals,
            "fail"=>$fails,
        ];
        return response()->json($res);
    }

    public function highPricesForeign(Request $request){
        $pricesforeign = $request->prices;
        $goals=[];
        $fails=[];
        $date_format = date("d/m/Y");
        foreach($pricesforeign as $price){

            
            $centro = "UPDATE F_LTA SET PRELTA = ". $price['CENTRO']." WHERE ARTLTA = ? AND TARLTA = 6";
            $exec = $this->conn->prepare($centro);
            $exec -> execute([$price["MODELO"]]);
   
           
            $especial = "UPDATE F_LTA SET PRELTA = ". $price['ESPECIAL']." WHERE ARTLTA = ? AND TARLTA = 5";
            $exec = $this->conn->prepare($especial);
            $exec -> execute([$price["MODELO"]]);

           
            $caja = "UPDATE F_LTA SET PRELTA = ". $price['CAJA']." WHERE ARTLTA = ? AND TARLTA = 4";
            $exec = $this->conn->prepare($caja);
            $exec -> execute([$price["MODELO"]]);
         
            
            $docena = "UPDATE F_LTA SET PRELTA = ". $price['DOCENA']." WHERE ARTLTA = ? AND TARLTA = 3";
            $exec = $this->conn->prepare($docena);
            $exec -> execute([$price["MODELO"]]);
           
            
            $mayoreo = "UPDATE F_LTA SET PRELTA = ". $price['MAYOREO']." WHERE ARTLTA = ? AND TARLTA = 2";
            $exec = $this->conn->prepare($mayoreo);
            $exec -> execute([$price["MODELO"]]);
     
            
            $menudeo = "UPDATE F_LTA SET PRELTA = ". $price['MENUDEO']." WHERE ARTLTA = ? AND TARLTA = 1";
            $exec = $this->conn->prepare($menudeo);
            $exec -> execute([$price["MODELO"]]);
        
            
            $costo = "UPDATE F_ART SET PCOART = ". $price['COSTO']." , FUMART = ".$date_format." WHERE CODART = ? ";
            $exec = $this->conn->prepare($costo);
            $exec -> execute([$price["MODELO"]]);
            
            if($exec){$goals['factusol'][]=$price['MODELO']." Precios Modificados factusol";}else{$fails['factusol']= $price['MODELO']." error al actualizar factusol";}

        
        }
        $res = [
            "goals"=>$goals,
            "fail"=>$fails,
        ];
        return response()->json($res);
    }

    public function insertPub(Request $request){
        $actualizados = [];
        $insertados = [];
        $date = date("Y/m/d H:i");
        $date_format = date("d/m/Y");
        $articulos = $request->articulos;
        $margen = 1.05;
        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($articulos as $art){
            
            $artexs = "SELECT CODART FROM F_ART WHERE CODART = ?";
            $exec = $this->conn->prepare($artexs);
            $exec -> execute([$art['CODART']]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);
            
            if($cossis){
                $articulo=$art['CODART'];
                $costo = round($art['PCOART']*$margen,2);
                $barcode = $art['EANART'];
                $familia = $art['FAMART'];
                $palets =$art['UPPART'];
                $categoria = $art['CP1ART'];
                $actualizar =[
                    $date_format,
                    $costo,
                    $barcode,
                    $familia,
                    $palets,
                    $categoria,
                    $articulo
                ];

                $updaxs = "UPDATE F_ART SET FUMART = ?, PCOART = ?, EANART = ?, FAMART = ?,  UPPART = ?, CP1ART = ? WHERE CODART = ?";
                $exec = $this->conn->prepare($updaxs);
                $exec -> execute($actualizar);
                $actualizados[] ="Se actualizo el modelo ".$cossis["CODART"]; 
            }else{
                $product = [
                    $art["CODART"],
                    $art["EANART"],
                    $art["FAMART"],
                    $art["DESART"],
                    $art["DEEART"],
                    $art["DETART"],
                    $art["DLAART"],
                    $art["EQUART"],
                    $art["CCOART"],
                    $art["PHAART"],
                    $art["REFART"],
                    $art["FTEART"],
                    ($art["PCOART"]*$margen),
                    $art["UPPART"],
                    $art["CANART"],
                    $art["CAEART"],
                    $art["UMEART"],
                    $art["CP1ART"],
                    $art["CP2ART"],
                    $art["CP3ART"],
                    $art["CP4ART"],
                    $art["CP5ART"],
                    $date_format,
                    $date_format,
                    $art["MPTART"],
                    $art["UEQART"]
                ];
            $artid = "INSERT INTO  F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,FALART,FUMART,MPTART,UEQART
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($artid);
            $exec -> execute($product);
            foreach($fil as $row){
                $alm=$row['CODALM'];
                $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                $exec = $this->conn->prepare($insertsto);
                $exec -> execute([$art["CODART"],$alm,0,0,0,0]);
            }
            $insertados[]="Se inserto el modelo ".$art["CODART"];
            } 

       }
        $res = [
            "insertados"=>$insertados,
            "actualizados"=>$actualizados
        ];
        return response()->json($res);

    }

    public function insertPricesPub(request $request){
        $actualizados = [];
        $insertados = [];

        $prices = $request->prices;


        $margen = 1.05;
        foreach($prices as $price){

            $articulo = $price['CODIGO'];
            $centro = round($price['CENTRO']*$margen,0);
            $especial = round($price['ESPECIAL']*$margen,0);
            $caja = round($price['CAJA']*$margen,0);
            $docena = round($price['DOCENA']*$margen,0);
            $mayoreo = round($price['MAYOREO']*$margen,0);

            if($mayoreo == $centro){
                $menudeo = $caja;
            }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                $menudeo = $mayoreo + 5;
            }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                $menudeo = $mayoreo + 10;
            }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                $menudeo = $mayoreo + 20;
            }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                $menudeo = $mayoreo + 50;
            }elseif($mayoreo >= 1000){
                $menudeo =  $mayoreo + 100; 
            }

            $exispr = "SELECT ARTLTA FROM F_LTA WHERE ARTLTA = ?";
            $exec = $this->conn->prepare($exispr);
            $exec -> execute([$articulo]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);
            if($cossis){
                
                $cen = "UPDATE F_LTA SET PRELTA = ".$centro." WHERE ARTLTA = ?  AND TARLTA = 6";
                $exec = $this->conn->prepare($cen);
                $exec -> execute([$articulo]);
    
                $espe = "UPDATE F_LTA SET PRELTA = ".$especial." WHERE ARTLTA = ? AND TARLTA = 5";
                $exec = $this->conn->prepare($espe);
                $exec -> execute([$articulo]);
                
                $caj = "UPDATE F_LTA SET PRELTA = ".$caja." WHERE ARTLTA = ? AND TARLTA = 4";
                $exec = $this->conn->prepare($caj);
                $exec -> execute([$articulo]);
            
                $doc = "UPDATE F_LTA SET PRELTA = ".$docena." WHERE ARTLTA = ? AND TARLTA = 3";
                $exec = $this->conn->prepare($doc);
                $exec -> execute([$articulo]);
        
                $mayo = "UPDATE F_LTA SET PRELTA = ".$mayoreo." WHERE ARTLTA = ?  AND TARLTA = 2";
                $exec = $this->conn->prepare($mayo);
                $exec -> execute([$articulo]);
            
                $menu = "UPDATE F_LTA SET PRELTA = ".$menudeo." WHERE ARTLTA = ? AND TARLTA = 1";
                $exec = $this->conn->prepare($menu);
                $exec -> execute([$articulo]);
                $actualizados[]="Se actuzalizaron precios de el articulo ".$articulo;
            }else{

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([6,$articulo,0,$centro]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([5,$articulo,0,$especial]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([4,$articulo,0,$caja]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([3,$articulo,0,$docena]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([2,$articulo,0,$mayoreo]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([1,$articulo,0,$menudeo]);
                $insertados[]="Precios insertados del articulo ".$articulo;
            }
        }
        $res = [
            "actualizados"=>$actualizados,
            "insertados"=>$insertados
        ];
        return response()->json($res);
    }

    public function insertPubProducts(request $request){
        $actualizados = [];
        $insertados = [];
        $date_format = date("d/m/Y");
        $articulos = $request->articulos;
        $margen = 1.05;
        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($articulos as $art){
            
            $artexs = "SELECT CODART FROM F_ART WHERE CODART = ?";
            $exec = $this->conn->prepare($artexs);
            $exec -> execute([$art['CODART']]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);
            if($cossis){
                $articulo=$art['CODART'];
                $costo = round($art['PCOART']*$margen,2);
                $barcode = $art['EANART'];
                $familia = $art['FAMART'];
                $palets =$art['UPPART'];
                $categoria = $art['CP1ART'];
                $actualizar =[
                    $date_format,
                    $costo,
                    $barcode,
                    $familia,
                    $palets,
                    $categoria,
                    $articulo
                ];

                $updaxs = "UPDATE F_ART SET FUMART = ?, PCOART = ?, EANART = ?, FAMART = ?,  UPPART = ?, CP1ART = ? WHERE CODART = ?";
                $exec = $this->conn->prepare($updaxs);
                $exec -> execute($actualizar);
                $actualizados[] ="Se actualizo el modelo ".$cossis["CODART"]; 
            }else{
                $product = [
                    $art["CODART"],
                    $art["EANART"],
                    $art["FAMART"],
                    $art["DESART"],
                    $art["DEEART"],
                    $art["DETART"],
                    $art["DLAART"],
                    $art["EQUART"],
                    $art["CCOART"],
                    $art["PHAART"],
                    $art["REFART"],
                    $art["FTEART"],
                    ($art["PCOART"]*$margen),
                    $art["UPPART"],
                    $art["CANART"],
                    $art["CAEART"],
                    $art["UMEART"],
                    $art["CP1ART"],
                    $art["CP2ART"],
                    $art["CP3ART"],
                    $art["CP4ART"],
                    $art["CP5ART"],
                    $date_format,
                    $date_format,
                    $art["MPTART"],
                    $art["UEQART"]
                ];
            $artid = "INSERT INTO  F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,FALART,FUMART,MPTART,UEQART
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($artid);
            $exec -> execute($product);
            foreach($fil as $row){
                $alm=$row['CODALM'];
                $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                $exec = $this->conn->prepare($insertsto);
                $exec -> execute([$art["CODART"],$alm,0,0,0,0]);
            }
            $insertados[]="Se inserto el modelo ".$art["CODART"];
            } 

       }
        $res = [
            "insertados"=>$insertados,
            "actualizados"=>$actualizados
        ];
        return response()->json($res);
    }

    public function insertPricesProductPub(request $request){
        $actualizados = [];
        $insertados = [];

        $prices = $request->prices;


        $margen = 1.05;
        foreach($prices as $price){

            $articulo = $price['CODIGO'];
            $centro = round($price['CENTRO']*$margen,0);
            $especial = round($price['ESPECIAL']*$margen,0);
            $caja = round($price['CAJA']*$margen,0);
            $docena = round($price['DOCENA']*$margen,0);
            $mayoreo = round($price['MAYOREO']*$margen,0);

            if($mayoreo == $centro){
                $menudeo = $caja;
            }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                $menudeo = $mayoreo + 5;
            }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                $menudeo = $mayoreo + 10;
            }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                $menudeo = $mayoreo + 20;
            }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                $menudeo = $mayoreo + 50;
            }elseif($mayoreo >= 1000){
                $menudeo =  $mayoreo + 100; 
            }

            $exispr = "SELECT ARTLTA FROM F_LTA WHERE ARTLTA = ?";
            $exec = $this->conn->prepare($exispr);
            $exec -> execute([$articulo]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);
            if($cossis){
                
                $cen = "UPDATE F_LTA SET PRELTA = ".$centro." WHERE ARTLTA = ?  AND TARLTA = 6";
                $exec = $this->conn->prepare($cen);
                $exec -> execute([$articulo]);
    
                $espe = "UPDATE F_LTA SET PRELTA = ".$especial." WHERE ARTLTA = ? AND TARLTA = 5";
                $exec = $this->conn->prepare($espe);
                $exec -> execute([$articulo]);
                
                $caj = "UPDATE F_LTA SET PRELTA = ".$caja." WHERE ARTLTA = ? AND TARLTA = 4";
                $exec = $this->conn->prepare($caj);
                $exec -> execute([$articulo]);
            
                $doc = "UPDATE F_LTA SET PRELTA = ".$docena." WHERE ARTLTA = ? AND TARLTA = 3";
                $exec = $this->conn->prepare($doc);
                $exec -> execute([$articulo]);
        
                $mayo = "UPDATE F_LTA SET PRELTA = ".$mayoreo." WHERE ARTLTA = ?  AND TARLTA = 2";
                $exec = $this->conn->prepare($mayo);
                $exec -> execute([$articulo]);
            
                $menu = "UPDATE F_LTA SET PRELTA = ".$menudeo." WHERE ARTLTA = ? AND TARLTA = 1";
                $exec = $this->conn->prepare($menu);
                $exec -> execute([$articulo]);
                $actualizados[]="Se actuzalizaron precios de el articulo ".$articulo;
            }else{

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([6,$articulo,0,$centro]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([5,$articulo,0,$especial]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([4,$articulo,0,$caja]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([3,$articulo,0,$docena]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([2,$articulo,0,$mayoreo]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([1,$articulo,0,$menudeo]);
                $insertados[]="Precios insertados del articulo ".$articulo;
            }
        }
        $res = [
            "actualizados"=>$actualizados,
            "insertados"=>$insertados
        ];
        return response()->json($res);
    }
 
    public function replyProducts(Request $request){
        $articulos = $request->articulos;
        $date_format = date("d/m/Y");
        $actualizados = [];
        $insertados = [];
        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);

        foreach($articulos as $articulo){
            $producto=[
                $articulo["EANART"],
                $articulo["DESART"],
                $articulo["DEEART"],
                $articulo["DETART"],
                $articulo["DLAART"],
                $articulo["EQUART"],
                $articulo["CCOART"],
                $articulo["PCOART"],
                $articulo["PHAART"],
                $articulo["REFART"],
                $articulo["FUMART"],
                $articulo["UPPART"],
                $articulo["UMEART"],
                $articulo["CP1ART"],
                $articulo["CP2ART"],
                $articulo["CP3ART"],
                $articulo["CP4ART"],
                $articulo["CP5ART"],
                $articulo["NPUART"],
                $articulo["NIAART"],
                $articulo["DSCART"],
                $articulo["MPTART"],
                $articulo["UEQART"],
                $articulo["CAEART"],
                $articulo["CANART"],
                $articulo['FAMART'],
                $articulo['CODART']
            ];
            $sql = "SELECT CODART FROM F_ART WHERE CODART = ?";
            $exec = $this->conn->prepare($sql);
            $exec -> execute([$articulo['CODART']]);
            $artic=$exec->fetch(\PDO::FETCH_ASSOC);
            if($artic){
                $upd = "UPDATE F_ART SET EANART = ? ,DESART = ? ,DEEART = ? ,DETART = ? ,DLAART = ? ,EQUART = ? ,CCOART = ? ,PCOART = ? ,PHAART = ? ,REFART = ? ,FUMART = ? ,UPPART = ? ,UMEART = ? ,CP1ART = ? ,CP2ART = ? ,CP3ART = ? ,CP4ART = ? ,CP5ART = ? ,NPUART = ? ,NIAART = ? ,DSCART = ? ,MPTART = ? ,UEQART = ? ,CAEART = ? ,CANART = ?, FAMART = ? WHERE CODART = ?";
                $exec = $this->conn->prepare($upd);
                $exec -> execute($producto);
                $actualizados[]="Articulo ".$articulo['CODART']." actualizado";
            }else{
                $ins = "INSERT INTO F_ART (EANART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PCOART,PHAART,REFART,FUMART,UPPART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,NPUART,NIAART,DSCART,MPTART,UEQART,CAEART,CANART,FAMART,CODART
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($ins);
                $exec -> execute($producto);
                foreach($fil as $row){
                    $alm=$row['CODALM'];
                    $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                    $exec = $this->conn->prepare($insertsto);
                    $exec -> execute([$articulo["CODART"],$alm,0,0,0,0]);
                }
                $insertados[]="Se inserto el modelo ".$articulo["CODART"];
            }
        }
        $res = [
            "insertados"=>$insertados,
            "actualizados"=>$actualizados
        ];
        return response()->json($res);
    }

    public function replyProductsPrices(Request $request){
        $prices = $request->prices;
        $actualizados = [];
        $insertados = [];
        foreach($prices as $price){

            $articulo = $price['CODIGO'];
            $centro = $price['CENTRO'];
            $especial = $price['ESPECIAL'];
            $caja = $price['CAJA'];
            $docena = $price['DOCENA'];
            $mayoreo = $price['MAYOREO'];
            $menudeo = $price['MENUDEO'];
            $exispr = "SELECT ARTLTA FROM F_LTA WHERE ARTLTA = ?";
            $exec = $this->conn->prepare($exispr);
            $exec -> execute([$articulo]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);
            if($cossis){
                
                $cen = "UPDATE F_LTA SET PRELTA = ".$centro." WHERE ARTLTA = ?  AND TARLTA = 6";
                $exec = $this->conn->prepare($cen);
                $exec -> execute([$articulo]);
    
                $espe = "UPDATE F_LTA SET PRELTA = ".$especial." WHERE ARTLTA = ? AND TARLTA = 5";
                $exec = $this->conn->prepare($espe);
                $exec -> execute([$articulo]);
                
                $caj = "UPDATE F_LTA SET PRELTA = ".$caja." WHERE ARTLTA = ? AND TARLTA = 4";
                $exec = $this->conn->prepare($caj);
                $exec -> execute([$articulo]);
            
                $doc = "UPDATE F_LTA SET PRELTA = ".$docena." WHERE ARTLTA = ? AND TARLTA = 3";
                $exec = $this->conn->prepare($doc);
                $exec -> execute([$articulo]);
        
                $mayo = "UPDATE F_LTA SET PRELTA = ".$mayoreo." WHERE ARTLTA = ?  AND TARLTA = 2";
                $exec = $this->conn->prepare($mayo);
                $exec -> execute([$articulo]);
            
                $menu = "UPDATE F_LTA SET PRELTA = ".$menudeo." WHERE ARTLTA = ? AND TARLTA = 1";
                $exec = $this->conn->prepare($menu);
                $exec -> execute([$articulo]);
                $actualizados[]="Se actuzalizaron precios de el articulo ".$articulo;
            }else{

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([6,$articulo,0,$centro]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([5,$articulo,0,$especial]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([4,$articulo,0,$caja]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([3,$articulo,0,$docena]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([2,$articulo,0,$mayoreo]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([1,$articulo,0,$menudeo]);
                $insertados[]="Precios insertados del articulo ".$articulo;
            }
        }
        $res = [
            "actualizados"=>$actualizados,
            "insertados"=>$insertados
        ];
        return response()->json($res);
    }

    public function additionalsBarcode(Request $request){
        $addbar = $request->addbarcodes;
     
        $ids = $request->ids;
        $clientms =  "DELETE FROM F_EAN WHERE ARTEAN IN (".implode(",",$ids).")";
        $exec = $this->conn->prepare($clientms);
        $exec -> execute();
            foreach($addbar as $con){
                $clsd =[
                    $con['ARTEAN'],
                    $con['EANEAN']
                ];
            $inset="INSERT INTO F_EAN (ARTEAN,EANEAN) VALUES(?,?)";
            $exec = $this->conn->prepare($inset);
            $exec -> execute($clsd);
        }
        return response()->json("Generado Correctamente");
     
    }

}    