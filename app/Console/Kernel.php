<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

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
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }

        $schedule->call(function (){ //replicador de ventas sucursales
            $workpoint = env('WKP');
            $date = now()->format("Y-m-d");
            $sday = DB::table('sales')->whereDate('created_at',$date)->where('_store',$workpoint)->get();
            if(count($sday) == 0){
                $sfsday = "SELECT TIPFAC&'-'&CODFAC AS TICKET, CLIFAC AS CLIENTE, CNOFAC AS NOMCLI, DEPFAC AS USUARIO, ALMFAC AS ALMACEN, TOTFAC AS TOTAL, FOPFAC AS FORMAP, FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS CREACION, TERFAC AS TERMINAL FROM F_FAC WHERE FECFAC =DATE()";
                $exec = $this->conn->prepare($sfsday);
                $exec -> execute();
                $fact=$exec->fetchall(\PDO::FETCH_ASSOC);
                if($fact){
                    foreach($fact as $fs){
                        $ptick[] = "'".$fs['TICKET']."'";
                        $client = DB::table('clients')->where('fs_id',$fs['CLIENTE'])->value('id');
                        $user = DB::table('users')->where('TPV_id',$fs['USUARIO'])->value('id');
                        $warehouse = DB::table('warehouses')->where('alias',$fs['ALMACEN'])->where('_store',$workpoint)->value('id');
                        $payment =DB::table('payment_methods')->where('alias',$fs['FORMAP'])->value('id');
                        $cash = DB::table('cash_registers')->where('terminal',$fs['TERMINAL'])->value('id');
                        $facturas  = [
                            "num_ticket"=>$fs['TICKET'],
                            "_client"=>$client,
                            "name"=>$fs['NOMCLI'],
                            "_user"=>$user,
                            "_store"=>intval($workpoint),
                            "_warehouse"=>$warehouse,
                            "total"=>$fs['TOTAL'],
                            "_payment"=>$payment,
                            "created_at"=>$fs['CREACION'],
                            "updated_at"=>now(),//->toDateTimeString(),
                            "_cash"=>$cash
                        ];
                        $insert = DB::table('sales')->insert($facturas);
                    }
                    $prday = "SELECT TIPLFA&'-'&CODLFA AS TICKET, ARTLFA AS ARTICULO, CANLFA AS CANTIDAD, PRELFA AS PRECIO, TOTLFA AS TOTAL, COSLFA AS COSTO FROM F_LFA WHERE TIPLFA&'-'&CODLFA IN (".implode(",",$ptick).")";
                    $exec = $this->conn->prepare($prday);
                    $exec -> execute();
                    $profac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    foreach($profac as $pro){
                        $sale = DB::table('sales')->where('num_ticket',$pro['TICKET'])->where('_store',$workpoint)->value('id');
                        $product = DB::table('products')->where('code',$pro['ARTICULO'])->first();
                        $produ = [
                            "_sale"=>$sale,
                            "_product"=>$product->id,
                            "amount"=>$pro['CANTIDAD'],
                            "price"=>$pro['PRECIO'],
                            "total"=>$pro['TOTAL'],
                            "COST"=>$product->cost
                        ];
                        $insert = DB::table('sale_bodies')->insert($produ);
                    }
                    $paday = "SELECT TFALCO&'-'&CFALCO AS TICKET, FORMAT(FECLCO,'YYYY-mm-dd')&' '&'00:00:00' AS CREACION, IMPLCO AS TOTAL,CPTLCO AS  CONCEPTO, FPALCO AS FAP, TERLCO AS TERMINAL FROM F_LCO WHERE TFALCO&'-'&CFALCO IN (".implode(",",$ptick).")";
                    $exec = $this->conn->prepare($paday);
                    $exec -> execute();
                    $payfac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    if($payfac){
                        $colsTab = array_keys($payfac[0]);//llaves de el arreglo
                        foreach($payfac as $paym){
                            foreach($colsTab as $col){ $paym[$col] = utf8_encode($paym[$col]); }
                            $salep = DB::table('sales')->where('num_ticket',$paym['TICKET'])->where('_store',$workpoint)->value('id');
                            $cashs = DB::table('cash_registers')->where('terminal',$paym['TERMINAL'])->value('id');
                            $payment = DB::table('payment_methods')->where('alias',$paym['FAP'])->value('id');
                            $pagos = [
                                "_sale"=>$salep,
                                "created_at"=>$paym['CREACION'],
                                "total"=>$paym['TOTAL'],
                                "concept"=>$paym['CONCEPTO'],
                                "_collection"=>$payment,
                                "_cash"=>$cashs
                            ];
                        }
                        $insert = DB::table('sale_collection_lines')->insert($pagos);
                        echo "Se añadieron ".count($fact)." facturas";
                    }
                }else{echo "No hay facturas que replicar bro";}
            }else{
                foreach($sday as $sale){
                    $fact[]="'".$sale->num_ticket."'";
                }
                $sfsday = "SELECT TIPFAC&'-'&CODFAC AS TICKET, CLIFAC AS CLIENTE, CNOFAC AS NOMCLI, DEPFAC AS USUARIO, ALMFAC AS ALMACEN, TOTFAC AS TOTAL, FOPFAC AS FORMAP, FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS CREACION, TERFAC AS TERMINAL FROM F_FAC WHERE FECFAC =DATE() AND  TIPFAC&'-'&CODFAC NOT IN (".implode(",",$fact).")";
                $exec = $this->conn->prepare($sfsday);
                $exec -> execute();
                $fact=$exec->fetchall(\PDO::FETCH_ASSOC);
                if($fact){
                    foreach($fact as $fs){
                        $ptick[] = "'".$fs['TICKET']."'";
                        $client = DB::table('clients')->where('fs_id',$fs['CLIENTE'])->value('id');
                        $user = DB::table('users')->where('TPV_id',$fs['USUARIO'])->value('id');
                        $warehouse = DB::table('warehouses')->where('alias',$fs['ALMACEN'])->where('_store',$workpoint)->value('id');
                        $payment =DB::table('payment_methods')->where('alias',$fs['FORMAP'])->value('id');
                        $cash = DB::table('cash_registers')->where('terminal',$fs['TERMINAL'])->value('id');
                        $facturas  = [
                            "num_ticket"=>$fs['TICKET'],
                            "_client"=>$client,
                            "name"=>$fs['NOMCLI'],
                            "_user"=>$user,
                            "_store"=>intval($workpoint),
                            "_warehouse"=>$warehouse,
                            "total"=>$fs['TOTAL'],
                            "_payment"=>$payment,
                            "created_at"=>$fs['CREACION'],
                            "updated_at"=>now(),//->toDateTimeString(),
                            "_cash"=>$cash
                        ];
                        $insert = DB::table('sales')->insert($facturas);
                    }
                    $prday = "SELECT TIPLFA&'-'&CODLFA AS TICKET, ARTLFA AS ARTICULO, CANLFA AS CANTIDAD, PRELFA AS PRECIO, TOTLFA AS TOTAL, COSLFA AS COSTO FROM F_LFA WHERE TIPLFA&'-'&CODLFA IN (".implode(",",$ptick).")";
                    $exec = $this->conn->prepare($prday);
                    $exec -> execute();
                    $profac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    foreach($profac as $pro){
                        $sale = DB::table('sales')->where('num_ticket',$pro['TICKET'])->where('_store',$workpoint)->value('id');
                        $product = DB::table('products')->where('code',$pro['ARTICULO'])->first();
                        $produ = [
                            "_sale"=>$sale,
                            "_product"=>$product->id,
                            "amount"=>$pro['CANTIDAD'],
                            "price"=>$pro['PRECIO'],
                            "total"=>$pro['TOTAL'],
                            "COST"=>$product->cost
                        ];
                        $insert = DB::table('sale_bodies')->insert($produ);
                    }
                    $paday = "SELECT TFALCO&'-'&CFALCO AS TICKET, FORMAT(FECLCO,'YYYY-mm-dd')&' '&'00:00:00' AS CREACION, IMPLCO AS TOTAL, CPTLCO AS  CONCEPTO, FPALCO AS FAP, TERLCO AS TERMINAL FROM F_LCO WHERE TFALCO&'-'&CFALCO IN (".implode(",",$ptick).")";
                    $exec = $this->conn->prepare($paday);
                    $exec -> execute();
                    $payfac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    if($payfac){
                        $colsTab = array_keys($payfac[0]);//llaves de el arreglo
                        foreach($payfac as $paym){
                            foreach($colsTab as $col){ $paym[$col] = utf8_encode($paym[$col]); }
                            $salep = DB::table('sales')->where('num_ticket',$paym['TICKET'])->where('_store',$workpoint)->value('id');
                            $cashs = DB::table('cash_registers')->where('terminal',$paym['TERMINAL'])->value('id');
                            $payment = DB::table('payment_methods')->where('alias',$paym['FAP'])->value('id');
                            $pagos = [
                                "_sale"=>$salep,
                                "created_at"=>$paym['CREACION'],
                                "total"=>$paym['TOTAL'],
                                "concept"=>$paym['CONCEPTO'],
                                "_collection"=>$payment,
                                "_cash"=>$cashs
                            ];
                        }
                        $insert = DB::table('sale_collection_lines')->insert($pagos);
                    }
                    echo "Se añadieron ".count($fact)." facturas";
                }else{echo "No hay facturas que replicar bro";}
            }
        })->everyMinute()->between('8:00','22:00');

        $schedule->call(function (){//replicador de stock de cedis se ejecuta cada minuto
            $factusol = [];
            $msql = [];
            $workpoint = env('WKP');
            try{
                $sto  = "SELECT * FROM (SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LFA ON F_LFA.ARTLFA  = F_STO.ARTSTO)
                    INNER JOIN F_FAC ON ( F_FAC.TIPFAC&'-'&F_FAC.CODFAC = F_LFA.TIPLFA&'-'&F_LFA.CODLFA AND F_FAC.FECFAC =DATE()) )
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LFR ON F_LFR.ARTLFR  = F_STO.ARTSTO)
                    INNER JOIN F_FRE ON (F_FRE.TIPFRE&'-'&F_FRE.CODFRE = F_LFR.TIPLFR&'-'&F_LFR.CODLFR  AND F_FRE.FECFRE =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LEN ON F_LEN.ARTLEN  = F_STO.ARTSTO)
                    INNER JOIN F_ENT ON (F_ENT.TIPENT&'-'&F_ENT.CODENT = F_LEN.TIPLEN&'-'&F_LEN.CODLEN  AND F_ENT.FECENT =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LFD ON F_LFD.ARTLFD  = F_STO.ARTSTO)
                    INNER JOIN F_FRD ON (F_FRD.TIPFRD&'-'&F_FRD.CODFRD = F_LFD.TIPLFD&'-'&F_LFD.CODLFD  AND F_FRD.FECFRD =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LAL ON F_LAL.ARTLAL  = F_STO.ARTSTO)
                    INNER JOIN F_ALB ON (F_ALB.TIPALB&'-'&F_ALB.CODALB = F_LAL.TIPLAL&'-'&F_LAL.CODLAL  AND F_ALB.FECALB =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LFB ON F_LFB.ARTLFB  = F_STO.ARTSTO)
                    INNER JOIN F_FAB ON (F_FAB.TIPFAB&'-'&F_FAB.CODFAB = F_LFB.TIPLFB&'-'&F_LFB.CODLFB  AND F_FAB.FECFAB =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LSA ON F_LSA.ARTLSA  = F_STO.ARTSTO)
                    INNER JOIN F_SAL ON (F_SAL.CODSAL = F_LSA.CODLSA  AND F_SAL.FECSAL =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LTR ON F_LTR.ARTLTR  = F_STO.ARTSTO)
                    INNER JOIN F_TRA ON (F_TRA.DOCTRA = F_LTR.DOCLTR  AND F_TRA.FECTRA =DATE()))
                    UNION
                    SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM (F_STO
                    INNER JOIN F_CIN ON (F_CIN.ARTCIN  = F_STO.ARTSTO AND F_CIN.FECCIN = DATE()))
                    UNION SELECT
                    F_STO.ARTSTO,
                    F_STO.ALMSTO,
                    F_STO.ACTSTO,
                    F_STO.DISSTO
                    FROM ((F_STO
                    INNER JOIN F_LFC ON F_LFC.ARTLFC  = F_STO.ARTSTO)
                    INNER JOIN F_FCO ON (F_FCO.CODFCO = F_LFC.CODLFC  AND F_FCO.FECFCO =DATE()))) ORDER BY F_STO.ALMSTO ASC,  F_STO.ARTSTO DESC";
                $exec = $this->conn->prepare($sto);
                $exec -> execute();
                $stocks=$exec->fetchall(\PDO::FETCH_ASSOC);
            }catch (\PDOException $e){ die($e->getMessage());}
            if($stocks){
                foreach($stocks as $stock){
                    $almas[]=$stock['ARTSTO'];
                    $articulos = [
                    'code'=>strtoupper($stock['ARTSTO']),
                    'alias'=>$stock['ALMSTO'],
                    '_current'=>intval($stock['ACTSTO']),
                    'available'=>intval($stock['DISSTO'])
                    ];
                    $factusol[]=$articulos;
                }

                $stockms = DB::table('product_stock AS PS')
                ->join('warehouses AS W','W.id','PS._warehouse')
                ->join('products AS P','P.id','PS._product')
                ->where('W._store',$workpoint)
                ->whereIn('P.code',array_unique($almas))
                ->select('P.code','W.alias','PS._current','PS.available')
                ->orderByRaw('W.alias ASC')
                ->orderByRaw('P.code DESC')
                ->get();
                foreach($stockms as $mss){
                    $arti = [
                        'code'=>strtoupper($mss->code),
                        'alias'=>$mss->alias,
                        '_current'=>$mss->_current,
                        'available'=>$mss->available
                    ];
                    $msql[]=$arti;
                }


                $out = array_udiff($factusol,$msql, function($a,$b){
                    if($a == $b){
                        return  0;

                    }else{
                        return ($a > $b) ? 1 : -1;
                    }
                });

                if($out){
                    foreach($out as $mod){
                        $cool = [
                            "_current"=>$mod['_current'],
                            "available"=>$mod['available']
                        ];
                    $update =  DB::table('product_stock AS PS')
                    ->join('warehouses AS W','W.id','PS._warehouse')
                    ->join('products AS P','P.id','PS._product')
                    ->where('W._store',$workpoint)
                    ->where('P.code',$mod['code'])
                    ->where('W.alias',$mod['alias'])
                    ->update($cool);
                    $mood[] = $mod['code'];
                    }
                    $res = count($mood);


                    echo "se actualizaron ".$res." filas del stock";
                }else{echo "No hay stock para replicar";}

            }else{echo "No hay stock para replicar";}

        })->everyMinute()->between('8:00','22:00');

        $schedule->call(function (){//replicador de retiradas de la sucursal
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
        })->everyThirtyMinutes()->between('8:00','22:00');

        $schedule->call(function (){//replicador de el reloj checador
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
                    $replicadas = count($assists);
                    $zk -> clearAttendance();
                    echo "se replicaron ".$replicadas." asistencias";
                }else{echo "No hay registros por el momento";}
            }else{echo "No hay conexion a el checador",501;}
        })->everyFiveMinutes()->between('8:00','22:00');

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
