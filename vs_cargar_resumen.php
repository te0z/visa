<?php

include 'php/pdf2text.php';

function clog ($msg) {
	echo "<script>console.log( '"."$msg"."' );</script>";  	
}

function LOGING($argumento) {
	// Guarda un registro del archivo cargado en la misma ruta en formato txt	
	global $archivodestino;
	$logfile = $archivodestino.".txt";
	file_put_contents($logfile,$argumento.PHP_EOL,FILE_APPEND);
}

function do_sql ($sql)
{			
	// Funcion simple ejecutar una query a motor db.
	include '../php/config.php';
	error_reporting(E_ERROR | E_PARSE);		
	if ($con) 
	{ 		
		$cuenta = substr_count($sql, "),('")+1;		
		$query = mysqli_query($con, $sql);				
		if ($query) {
			LOGING("");
			LOGING("Cargando $cuenta valores:");
			LOGING($sql);	
			mysqli_close($con);
			return array(true, "$sql");
		}
		else {			
			$errormsg = mysqli_error($con);
			if (strpos($errormsg,'Duplicate entry') !== false) {
				if (strpos($errormsg,"for key 'NroResumen_Unico'") !== false) { // Duplicate entry '856001989643' for key 'NroResumen_Unico'					
					$msg = "Este resumen ya está cargado en la base de datos.";					
				}				
			}			
			LOGING($errormsg);	
			mysqli_close($con); 
			return array(false, $msg);
		}		

	} else {
		LOGING("No se pudo conectar a la base de datos"); 
		return array(false, "No se pudo conectar a la base de datos");		
	}		
	
}

function array_todb ($array,$inicial,$totalcolumnas,$step,$sqlcode) {
	// Funcion generica para cargar campos almacenados en un array a una base de datos.
    unset($array_tovalues);  // re utilizo
    unset($valores);  // re utilizo
    $array_tovalues = array();
    $valores = array();
    $sql = $sqlcode;

    if (is_array($array)) {    
        foreach ($array as $key => $val) {       
            if (!empty($inicial)) { 
                $sql_valores = $inicial;
            } else {
                $sql_valores = '';
            }
            for ($col = 0; $col < $totalcolumnas; $col++) { 
                $sql_valores .= $array[$key][$col];
            }
        $array_tovalues[] = "(".$sql_valores.")";
        }
    }
    $a = 0;
    for ($i = 0; $i < count($array_tovalues); $i++) {
        $a++;
        $valores[] = $array_tovalues[$i];
        if ($a >= $step) {
            $valores  = implode(",",$valores);            
            $sql .= " VALUES $valores";
            if (!do_sql($sql)[0]) {
            	LOGING("Fallo en la carga de la siguiente sentencia SQL:");
            	LOGING($sql);
				$RPTA['MSG'] .= "<span style=\"color: red;\"><strong>A-T-DB Fallo algo con la ejecucion SQL! ver log.</strong></span><br>";
			}
			//            echo $sql."<br>";
            $sql = $sqlcode;
            $valores = null;            
            $a = 0;
        }
    }
    if ($valores) {
        $valores  = implode(",",$valores);
        $sql .= " VALUES $valores";
        if (!do_sql($sql)[0]) {
        	LOGING("Fallo en la carga de la siguiente sentencia SQL:");
        	LOGING($sql);
			$RPTA['MSG'] .= "<span style=\"color: red;\"><strong>A-T-DB Fallo algo con la ejecucion SQL! ver log.</strong></span><br>";
		}
		//        echo $sql."<br>";
        $sql = $sqlcode;
        $valores = null;
    }
}

function currdb ($val) {
	// Conversor de formato a moneda para db. Ej. 1.005.000,05 => 10005000.5
    return floatval(str_replace(',', '.', str_replace('.', '', str_replace('$', '', $val))));
}

function datewy ($date) {
    // Añade el año a la fecha ingresada en formato 30/12.
    $fecha_p = explode("/",$date);
    $mesactual = date("n");
    $a = (int) $fecha_p[1];
    $b = (int) $fecha_p[0];
    $mespre = date("n",mktime(0,0,0,$a,$b,date("Y")));
    if ($mespre > $mesactual) {                
        $año = date("Y",strtotime("-1 year"));
    } else {
        $año = date("Y");
    }
    return $date."/".$año;           
}

function datedb ($v) {
	// Devuele la expresion en formato compatible al campo en base de datos.
    $dat = explode("/",$v); // 31/01/2017'
    return "'"."$dat[2]/$dat[1]/$dat[0]"."'"; // 2017/01/31
}

function is_Date($str){ 
        
        $str = str_replace('/', '-', $str);     
        $stamp = strtotime($str);
        if (is_numeric($stamp)){  
            
            $month = date( 'm', $stamp ); 
            $day   = date( 'd', $stamp ); 
            $year  = date( 'Y', $stamp ); 
            
            return checkdate($month, $day, $year); 
                
        }  
        return false; 
    }

global $archivodestino;

if(isset($_FILES["FileInput"]) && $_FILES["FileInput"]["error"]== UPLOAD_ERR_OK) {	
	$dir_archivos	= '../archivos/vs_resumen/'.date("Y").'/'.date("Ymd").'/'; //specify upload directory ends with / (slash)
	if(!is_dir($dir_archivos)) {
		mkdir($dir_archivos, 0777, true);
		chmod($dir_archivos, 0777);
	}
	
	if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])){ die(); }
	
	if ($_FILES["FileInput"]["size"] > 5242880) { die("El tamaño del archivo supera los 5mb!"); }	
	
	switch(strtolower($_FILES['FileInput']['type']))
		{
			case 'application/pdf':
			break;
			default:
				die('Archivo no soportado, debe ser pdf: '.$_FILES['FileInput']['type']); //output error
	}	

	$archivobase 		= pathinfo($_FILES['FileInput']['name']);
	//$nomarchivo 		= str_replace(' ', '',  $archivobase['filename']); 
	$stamp 				= date('Ymd_His');
	//$cantlineas 		= count(file($_FILES['FileInput']['tmp_name']));
	$archivodestino 	= $dir_archivos.$stamp.'_VISA_RESUMEN.pdf'; //new file name
	
	if(move_uploaded_file($_FILES['FileInput']['tmp_name'], $archivodestino )) {
		// Verifico el archivo
		chmod($archivodestino, 0644);
		$fila = 1;
		$verificoarchivo = fopen($archivodestino, "r");
		
		if ($verificoarchivo) { // Verifico si puedo leer el archivo

			fclose($verificoarchivo); // cierro el archivo 

			$RPTA['MSG'] = '';
   			$RPTA['MSG'] .= "<span style=\"color: green;\"><strong>El archivo no contiene errores!</strong></span><br>";

			$result = pdf2text($archivodestino);

			// Reemplazo porciones de texto para que sea leible en caso de ver el contenido en pantalla.
			$var = str_replace('_________________________________________________', PHP_EOL,$result);			
			$var = str_replace('PERIODOLIQUIDADO', 'Periodo_liquidado '.PHP_EOL,$var);
			$var = str_replace('PLAZODEPAGODEBITO', 'Plazo_pag_debito '.PHP_EOL,$var);
			$var = str_replace('PLAZODEPAGO', 'Plazo_de_pag '.PHP_EOL,$var);
			$var = str_replace('RESP./CARACTER:', 'RESPCARACT',$var);
			$var = str_replace('FECHADEPAGO',PHP_EOL.'Fecha_de_pag: '.PHP_EOL,$var);
			$var = str_replace('Fechadepresentacin', 'Fecha_de_presentacion: '.PHP_EOL,$var);
			$var = str_replace('Arancel$', 'Arancel_$ '.PHP_EOL,$var);
			$var = str_replace('Deduc.Impositivas$', 'Deduc._Impositivas_$ '.PHP_EOL,$var);
			$var = str_replace('Liq.N', 'Liq_Nro: '.PHP_EOL,$var);
			$var = str_replace('-LoteN', PHP_EOL.' Lote_Nro: '.PHP_EOL,$var);
			$var = str_replace('Ventaen', PHP_EOL.' Venta_en '.PHP_EOL,$var);
			$var = str_replace('Ventasen',PHP_EOL.' Ventas_en '.PHP_EOL,$var);
			$var = str_replace('Totaldelda', 'Total_del_dia:',$var);
			$var = str_replace('pago',PHP_EOL.' pago',$var);
			$var = str_replace('VentasTj.Dbito',PHP_EOL.' Ventas_Tj.Dbito',$var);
			$var = str_replace('VentaTj.Dbito',PHP_EOL.' Venta_Tj.Dbito',$var);
			$var = str_replace(chr(9),PHP_EOL,$var);			
			$var = preg_split("/\\r\\n|\\r|\\n/",$var);

			// Inicializo variables y arrays.
			$pagos = Array();
			$liqs = Array();
			$retenciones = Array();
			$valretenciones = Array();			
			$totalret = 0;
			$mostrarparseo = false; // En caso de querer ver el contenido en pantalla.
			
			//*************************************************************
			// Comienzo a parsear los datos del PDF.
			//*************************************************************

			if (is_array($var)) { // 1ER PARSEO
			    foreach ($var as $key => $val) {

			      	$valor = $var[$key];
			      	
			      	// Funcion para mostrar lo que va leyendo en pantalla
			      	if ($mostrarparseo) {
				      	if (!empty($val)) {
				      		echo "KEY: ".($key)." VALOR: " . $val . "<br>";  
				      	} else {
				      		echo "<br>";
				      	}
				    }

			      	// *****************************************************
			        // Leo los datos de los PAGOS
					// *****************************************************
					
			        if (strpos($valor,'Fecha_de_pag') !== false) {
			            $indice = $key+1;
			            $fechadepago = datedb(datewy($var[$indice]));
			        }
			        if (strpos($valor,'Arancel_$') !== false) {
			            $indice = $key+1;
			            $arancel = currdb($var[$indice]);		        
			        }
			        if (strpos($valor,'Deduc._Impositivas_$') !== false) {
			            $indice = $key+1;            
			            $dimpositivas = currdb($var[$indice]);		        
			            $pagos[] = Array("$fechadepago",",$arancel",",$dimpositivas");
			        }
			        if (strpos($valor,'Fecha_de_presentacion') !== false) {
			            $indice = $key+1;
			            $fecha_pre = datedb(datewy($var[$indice])); // 05/12 o 30/12
			        }
					
					// *****************************************************
			        // Leo los datos de las liquidaciones y lotes de los pagos
			        // *****************************************************
			        
			        if (strpos($valor,'Liq_Nro') !== false) {
			            $indice = $key+1;
			            $liqnro = $var[$indice];
			        }
			        if (strpos($valor,'Lote_Nro') !== false) {
			            $indice = $key+1;
			            $lotenro = $var[$indice];
			        }
			        if ((strpos($valor,'Venta_en') !== false) || (strpos($valor,'Ventas_en') !== false) || (strpos($valor,'Ventas_Tj.Dbito') !== false) || (strpos($valor,'Venta_Tj.Dbito') !== false)) {            
			            $indice = $key-1;
			            $ventas = $var[$indice];		        
			        }
			        if  ((strpos($valor,'Venta_en') !== false) || (strpos($valor,'Ventas_en') !== false)) {
			            $indice = $key+1;
			            $cuotas = $var[$indice];
			            $indice = $key+3;
			            $monto = currdb($var[$indice]);
			            $medio = '"VC"';		        
			            $liqs[] = array("$fechadepago",",$fecha_pre",",$liqnro",",$lotenro",",$ventas",",$medio",",$cuotas",",$monto");
			        }
			        if ((strpos($valor,'Ventas_Tj.Dbito') !== false) || (strpos($valor,'Venta_Tj.Dbito') !== false)) {
			            $indice = $key+1;
			            $medio = '"VD"';
			            $monto = currdb($var[$indice]);		        
		             	$liqs[] = array("$fechadepago",",$fecha_pre",",$liqnro",",$lotenro",",$ventas",",$medio",",1",",$monto");
			        }		        
					
			        // *****************************************************
			        // Leo los datos del resumen
					// *****************************************************
					
			        if (strpos($valor,'FECHADEEMISION') !== false)  {
			            $indice = $key+12; 
			            $fechaemision = datedb($var[$indice]);
			        }
			        if (strpos($valor,'Nro.DERESUMEN') !== false)  {
			            $indice = $key+12; 
			            $nroresumen = $var[$indice];		            
			        }
			        if (strpos($valor,'PAGADOR') !== false)  {
			            $indice = $key+12; 
			            $pagador = $var[$indice];		            
			        }
			        if (strpos($valor,'SUCURSAL') !== false)  {
			            $indice = $key+12; 
			            $sucursal = $var[$indice];		            
			        }
			        if (strpos($valor,'DOMICILIO') !== false)  {
			            $indice = $key+12; 
			            $domicilio = $var[$indice];		            
			        }
			        if (strpos($valor,'Nro.deCUIT') !== false)  {
			            $indice = $key+12; 
			            $NrodeCUIT = $var[$indice];		    
			        }
			        if (strpos($valor,'RESPCARACT') !== false)  {
			            $indice = $key+12; 
			            $RESPCARACTER = $var[$indice];		   
			        }
			        if (strpos($valor,'Nro.CUITESTABLEC.') !== false)  {
			            $indice = $key+13; 
			            $cuitestablec = $var[$indice];		   
			        }
			        if (strpos($valor,'CARACTER') !== false)  {
			            $indice = $key+13; 
			            $caractestble = $var[$indice];		    
			        }
			        if (strpos($valor,'Nro.ING.BRUTOS') !== false)  {
			            $indice = $key+13; 
			            $iibb = $var[$indice];		     
			        }
			        if (strpos($valor,'Nro.DEESTABLECIMIENTO') !== false)  {
			            $indice = $key+13; 
			            $nroestablecimiento = $var[$indice];		   
			        }
			        if (strpos($valor,'RazonSocial') !== false)  {
			            $indice = $key+1; 
			            $razonsocial = $var[$indice];
			            $indice = $key+3; 
			            $rzestable = $var[$indice];            
			            $indice = $key+4; 
			            $est_dire = $var[$indice];
			            $indice = $key+5; 
			            $cp_region = $var[$indice];
			            $indice = $key+6; 
			            $provincia = $var[$indice];
			        }
			        if (strpos($valor,'TOTALPRESENTADO$') !== false)  {
			            $indice = $key+2; 
			            $totalpresentadopesos = $var[$indice];		            
			        }		                
			        if (strpos($valor,'TOTALPRESENTADOU$S') !== false)  {
			            $indice = $key+2; 
			            $totalpresentadodolares = $var[$indice];		            
			        }
			        if (strpos($valor,'TOTALDESCUENTO$') !== false)  {
			            $indice = $key+2; 
			            $totaldescuentopesos = $var[$indice];		            
			            $indice = $key+4; 
			            $netopercibidopesos = $var[$indice];
			        }
			        if (strpos($valor,'TOTALDESCUENTOU$S') !== false)  {
			            $indice = $key+2; 
			            $totaldescuentodolares = $var[$indice];            
			            $indice = $key+4; 
			            $netopercibidodolares = $var[$indice];            
			        }
			        if ((strpos($valor,'Plazo_de_pag') !== false) && (!isset($plazopago_vc))) {
			            $indice = $key+1; 
			            $plazopago_vc = $var[$indice];
			            $intdata_vc = preg_replace("/[^0-9_\s]/", "", $plazopago_vc);		            
			            if (strpos($plazopago_vc,'dashbiles') !== false) { $plazo_vc = 'dias'; }  
			            if (strpos($plazopago_vc,'horashbiles') !== false) { $plazo_vc = 'horas'; } 		   
			        }
			        if ((strpos($valor,'Plazo_pag_debito') !== false) && (!isset($plazopago_vd))) {
			            $indice = $key+1; 
			            $plazopago_vd = $var[$indice];
			            $intdata_vd = str_replace('dashbiles', '', $plazopago_vd); // capturo numero
			            $intdata_vd = str_replace('horashbiles', '', $plazopago_vd); // capturo numero
			            if (strpos($plazopago_vd,'dashbiles') !== false) { $plazo_vd = 'dias'; }
			            if (strpos($plazopago_vd,'horashbiles') !== false) { $plazo_vd = 'horas'; }		
			        }
			        if ((strpos($valor,'Periodo_liquidado') !== false) && (!isset($periodo))) {
			            $indice = $key+1; 
			            $periodo = $var[$indice];		            
			        }
					
					// *****************************************************
			        // Comienzo a leer las retenciones 
			        // *****************************************************
					
					if (strpos($valor,'NLIQUIDACION') !== false) { 					
						$fret = true;  // Flag de retenciones
					}
					
					if ($fret) {
						
				        /*==================================================================================================
				        Caso 1
				        [1419] => TotalRegimen213
	    				[1420] => $13.261,36
	    				*/
				        if (strpos($valor,'TotalRegimen') !== false) {
							$tr = (string) $var[$key+1];
							if ( (substr($tr, 0, 1) == '$') && (strlen($tr) > 1)) { // si $tr es '$205,03'		                		
		                		$totalret = currdb($tr);
			                } 
			                //$totalretgan = currdb($var[$key+1]);
			                $nomenclatura = 'RET';
			                $regimen = (string) substr($valor, 12, 4);
			                $tipo = $nomenclatura.$regimen; // NOMENCLATURA + EL TIPO DE RETENCION

			                if ($regimen == "213") { // Para la tabla donde se guarda la info del resumen
			                	$totalretgan = $totalret;
			                	//echo "$regimen ==> Ganancias Total: $totalretgan</br></br>";
			                	$totalret = 0;
			                } else {
			                	$totalretIVA = $totalret;
			                	//echo "$regimen ==> IVA Total: $totalretIVA</br></br>";
			                	$totalret = 0;
			                }

            				/* Para parsear el array de valores */						                  	

						    if (is_array($valretenciones)) {    
						        foreach ($valretenciones as $key => $val) {
						        	// Capturo los 3 valores guardados y buffereo 
						        	
						        	if ($valretenciones[$key][0] != '') { // Por si la fecha está vacia, descarto										            
						        		$a = datedb($valretenciones[$key][0]);
						        		$b = $valretenciones[$key][1];
						        		$c = $valretenciones[$key][2];
						        	
						        		// Cargo al array de retenciones
						        		
						        		$retenciones[] = array("$a",",$b",",$c",",'$tipo'");
						            }
						        }
						    }		
						    unset($valretenciones); // limpio el array
							$valretenciones = array(); // re inicializo 
			                $fret = false; // Flag de lectura de retenciones. 
						}
						/*==================================================================================================*/

			            $val = $var[$key]; // 02/01/2017349989
			            $dval = substr($val, 0, 10); // 02/01/2017 dia/mes/año
			            $liqval = substr($val, 10, 10); // 02/01/2017[349989] Nro de liquidacion
						
						/*==================================================================================================
						Valido la fecha
						[1417] => [31/03/2017]304693
						*/
			            if ((is_Date($dval)) && (strlen($dval) == 10) && (strlen($val) <= 18)) {
			            		$fecharet = $dval;
			            }

			            /*==================================================================================================
						Valido nro de liquidacion
						[1417] => 31/03/2017[304693]
						*/
			            if (is_numeric($liqval) && (is_Date(substr($val, 0, 10))) && (strlen($val) <= 18)) {
			            	$liq = $liqval;
			            	$nextcamp = $var[$key+1];
			            	if (substr($nextcamp, 0, 1) != '$') { // Si el siguiente campo no empieza con $ entonces no existe importe
								$valretenciones[] = array("$fecharet","$liq","0");
								unset($fecharet,$liq);
							}
			        	}

			        	/*==================================================================================================
			        	Capturo importe
			        	Caso 1
			        	[1066] => 31/05/2017175431
    					[1067] ===> $
    					[1068] => 21.368,30    					
			        	*/
	        			if ((strlen($val) < 2) && ($val == '$')) { // si $val es solo '$' (1067)	        			
	        				$retval = currdb($var[$key+1]); // Capturo 21.638,30 (1068)
	        				if ((is_numeric($retval)) && (strpos($retval,'/') !== true)) { // Evaluo que no sea fecha
	        					$ret_importe = $retval;
	        					$totalret = $totalret+$ret_importe; // incremento el total
	        					if (isset($fecharet) && isset($liq)) { // compruebo que previamente exista una liquidacion
									$valretenciones[] = array("$fecharet","$liq","$ret_importe");
								}							
	        				} 
	        			} 
	        			/*
	        			Capturo importe
    					Caso 2 
    					[1401] => 20/03/2017310236
    					[1402] ===> $205,03
    					*/
	        			if ( (substr($val, 0, 1) == '$') && (strlen($val) > 1)) { // si $val es '$205,03'
	        				$retval = currdb(substr($val, 1, 15)); // Capturo el numero en forma base de datos con currdb()
	        				if ((is_numeric($retval)) && (strpos($retval,'/') !== true)) {
	        					$ret_importe = $retval;	        					
	        					$totalret = $totalret+$ret_importe; // incremento el total	        					
	        					if (isset($fecharet) && isset($liq)) {
									$valretenciones[] = array("$fecharet","$liq","$ret_importe");
								}							
	        				} 
	        			}
	        		}

		    	}   // fin de For EACH - fin de lectura -
			} // FIN de 1ER PARSEO (is_array)

			//*************************************************************
			// Comienzo insercion de valores en base de datos
			//*************************************************************

			$sqlresumen = "INSERT INTO `visa_resumen`(`NroResumen`,`FechaEmision`,`Pagador`,`Sucursal`,`Domicilio`,`NroCUIT`,`CaracterIVA`,`EstNroCUIT`,`EstCaracterIVA`,`NroIIBB`,`EstNro`,`RazonSocial`,`EstRazonSocial`,`EstDireccion`,`RegionCP`,`Provincia`,`TotalPreARP`,`TotalDescARP`,`NetoPerARP`,`TotalPreUSD`,`TotalDescUSD`,`NetoPerUSD`,`PeriodoLiq`,`PlazoDebitoVal`,`PlazoDebitoMed`,`PlazoCredVal`,`PlazoCredMed`,`TotalRetIVA`,`TotalRetGan`) VALUES ($nroresumen,$fechaemision,'$pagador','$sucursal','$domicilio','$NrodeCUIT','$RESPCARACTER','$cuitestablec','$caractestble',$iibb,$nroestablecimiento,'$razonsocial','$rzestable','$est_dire','$cp_region','$provincia',".currdb($totalpresentadopesos).",".currdb($totaldescuentopesos).",".currdb($netopercibidopesos).",".currdb($totalpresentadodolares).",".currdb($totaldescuentodolares).",".currdb($netopercibidodolares).",'$periodo','$intdata_vd','$plazo_vd','$intdata_vc','$plazo_vc','$totalretIVA','$totalretgan')";

			$query_resumen = do_sql($sqlresumen);

		    if ($query_resumen[0]) {
				// si la query de ingreso del resumen dio OK, cargo lo demás
				$RPTA['MSG'] .= "<span style=\"color: green;\"><strong>El resumen se cargo correctamente.</strong></span><br>";
				array_todb($pagos,"$nroresumen,",3,15,"INSERT INTO `visa_pagos`(`NroResumen`, `FechaPago`, `Arancel`, `Deducciones`)");
			    array_todb($liqs,"$nroresumen,",8,15,"INSERT INTO `visa_presentaciones`(`NroResumen`,`FechaPago`,`FechaPre`, `NroLiq`, `NroLote`, `CantVentas`, `Medio`, `EnPagos`, `Monto`)");
			    array_todb($retenciones,"$nroresumen,",4,20,"INSERT INTO `visa_retenciones`(`NroResumen`, `Fecha`, `NroLiq`, `Importe`, `Tipo`)");
			
			} // fin de query
			else {
				$RPTA['MSG'] .= "<span style=\"color: red;\"><strong>No se pudo cargar el resumen!</strong></span><br>";
				$RPTA['MSG'] .= "<span style=\"color: red;\"><strong>Motivo: ".$query_resumen[1]."</strong></span><br>";
				
			}
			

		} // fin de verificacion de lectura de archivo.
		else {
	    	$RPTA['MSG'] = '';
			$RPTA['MSG'] .= "<span style=\"color: red;\"><strong>El archivo contiene errores! No se puede leer.</strong></span><br>";
    		unlink($archivodestino); //Borro el archivo con errores.        		
   		}

   		// Preparo resultado
   		$respuesta = '';
		foreach ($RPTA as $linea=>$mensaje)
   			{	   		
   				$respuesta .= $mensaje;
   			}
   		// Entrego resultado
		die($respuesta);
	} else { 
		die('Error subiendo el archivo!');
		}	
} else {
	die('Algo salio mal con la carga! No se selecciono ningun archivo?');
	}