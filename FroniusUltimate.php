<?php


// Originally authored by Terence Eden. https://shkspr.mobi/blog/2014/11/fronius-and-pvoutput/ 
// Smart Meter support added by B33st - https://github.com/b33st/Fronius_PVOutput_Uploader 
// Added 3Phase support and every API option, Easier configurability and works with or without threephase and meter. Pimped by myself B-Man https://github.com/B-Mqn/FroniusPHPUltimate/edit/master/FroniusUltimate.php

// Cron  setup// | crontab -e | */5 * * * * /usr/bin/php /home/pi/froniusUltimate.php


// Configuration Options
$dataManagerIP = "192.168.1.100";	//Fronius Lan IP Address
$dataFile = "fronius";											//this is for meter data but i dont have meter
$pvOutputApiKEY = "PVoutputAPI";	//PVOutput Api Key found in https://pvoutput.org/account.jsp					
$pvOutputSID = "SystemID";		//Your pvoutput SystemID
//													//add Timezone info here??		
$country = "Australia";
$capitalCity ="Adelaide";			



//Settings that can be Used in V7-V12	
// Inverter					inverterVoltageLive	inverterDCVoltage	inverterHz	inverterACAmps	inverterDCAmps
// ThreePhase  				phase1Volts	phase2Volts	phase3Volts	phase1Amps	phase2Amps	phase3Amps	
// Meter					meterExportDayTotal	meterImportDayTotal	meterPowerLive	meterPowerLiveExport	meterPowerLiveImport

$v7 = "phase1Volts";
$v8 = "phase2Volts";
$v9 = "phase3Volts";
$v10 = "phase1Amps";
$v11 = "phase2Amps";
$v12 = "phase3Amps";




// !!!!!!!!!!!!!!!! DONT EDIT BELOW HERE !!!!!!!!!!!!!!!!!!!

// Define Date & Time
date_default_timezone_set("$country/$capitalCity");
$system_time= time();
$date = date('Ymd', time());
$time = date('H:i', time());


// Inverter & Smart Meter API URLs
$pvOutputApiURL = "http://pvoutput.org/service/r2/addstatus.jsp?";
$inverterDataURL = "http://".$dataManagerIP."/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceID=1&DataCollection=CommonInverterData";
$meterDataURL = "http://".$dataManagerIP."/solar_api/v1/GetMeterRealtimeData.cgi?Scope=Device&DeviceId=0";
$threePhaseInverterDataURL = "http://".$dataManagerIP."/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceID=1&DataCollection=3PInverterData";


// Read Inverter Data
sleep(5);
$inverterJSON = file_get_contents($inverterDataURL);
$inverterData = json_decode($inverterJSON, true);
//
$inverterPowerLive = $inverterData["Body"]["Data"]["PAC"]["Value"];
$inverterEnergyDayTotal = $inverterData["Body"]["Data"]["DAY_ENERGY"]["Value"];
$inverterVoltageLive = $inverterData["Body"]["Data"]["UAC"]["Value"];
$inverterHz = $inverterData["Body"]["Data"]["FAC"]["Value"];
$inverterACAmps = $inverterData["Body"]["Data"]["IAC"]["Value"];
$inverterDCAmps = $inverterData["Body"]["Data"]["IDC"]["Value"];
$inverterDCVoltage = $inverterData["Body"]["Data"]["UDC"]["Value"];



// Read threePhaseInverter Data
sleep(5);
$threePhaseInverterJSON = file_get_contents($threePhaseInverterDataURL);
$threePhaseInverterData = json_decode($threePhaseInverterJSON, true);

if (isset($threePhaseInverterData["Body"]["Data"]["UAC_L1"]["Value"])) {
    echo "3phase inverter found\n";

$phase1Volts = $threePhaseInverterData["Body"]["Data"]["UAC_L1"]["Value"];
$phase2Volts = $threePhaseInverterData["Body"]["Data"]["UAC_L2"]["Value"];
$phase3Volts = $threePhaseInverterData["Body"]["Data"]["UAC_L3"]["Value"];
$phase1Amps = $threePhaseInverterData["Body"]["Data"]["IAC_L1"]["Value"];
$phase2Amps = $threePhaseInverterData["Body"]["Data"]["IAC_L2"]["Value"];
$phase3Amps = $threePhaseInverterData["Body"]["Data"]["IAC_L3"]["Value"];
} else {
echo "No 3Phase Inverter Found\n";
}


// Read Meter Data
//
    sleep(5);
   $meterJSON = file_get_contents($meterDataURL);
   $meterData = json_decode($meterJSON, true);			
											//add if $phase1Volts is defined do below else after working out meter minus generation crap and just work out consumption
if (isset($meterData["Body"]["Data"]["PowerReal_P_Sum"])) {
    echo "Smart meter found\n";
do {
    $meterPowerLive = $meterData["Body"]["Data"]["PowerReal_P_Sum"];
    $meterImportTotal = $meterData["Body"]["Data"]["EnergyReal_WAC_Plus_Absolute"];
    $meterExportTotal = $meterData["Body"]["Data"]["EnergyReal_WAC_Minus_Absolute"];
} while (empty($meterPowerLive) || empty($meterImportTotal) || empty($meterExportTotal));


// Read Previous Days Meter Totals From Data File
if (file_exists($dataFile)) {
    echo "Reading data from $dataFile\n";
} else {
    echo "The file $dataFile does not exist, creating... \n";
    $saveData = serialize(array('import' => $meterImportTotal, 'export' => $meterExportTotal));
    file_put_contents($dataFile, $saveData);
}
$readData = unserialize(file_get_contents($dataFile));
$meterImportDayStartTotal = $readData['import'];
$meterExportDayStartTotal = $readData['export'];

// Calculate Day Totals For Meter Data
$meterImportDayTotal = $meterImportTotal - $meterImportDayStartTotal;
$meterExportDayTotal = $meterExportTotal - $meterExportDayStartTotal;

// Calculate Consumption Data												//does it depend if net or gross? work out have a net or gross $ at top??? test as is first looks like 1B not 1A?
$consumptionPowerLive = $inverterPowerLive + $meterPowerLive;
$consumptionEnergyDayTotal = $inverterEnergyDayTotal + $meterImportDayTotal - $meterExportDayTotal;

// Calculate Live Import/Export Values
if ($meterPowerLive > 0) {
    $meterPowerLiveImport = $meterPowerLive;
    $meterPowerLiveExport = 0;
} else {
    $meterPowerLiveImport = 0;
    $meterPowerLiveExport = $meterPowerLive;
}


// Push to PVOutput
$pvOutputURL = $pvOutputApiURL
                . "key=" .  $pvOutputApiKEY
                . "&sid=" . $pvOutputSID
                . "&d=" .   $date
                . "&t=" .   $time
                . "&v1=" .  $inverterEnergyDayTotal
                . "&v2=" .  $inverterPowerLive
                . "&v3=" .  $consumptionEnergyDayTotal
                . "&v4=" .  $consumptionPowerLive
                . "&v6=" .  $inverterVoltageLive
                . "&v7=" .  $$v7
                . "&v8=" .  $$v8
                . "&v9=" .  $$v9
                . "&v10=" . $$v10
                . "&v11=" . $$v11
                . "&v12=" . $$v12;
file_get_contents(trim($pvOutputURL));													


//Print Values to Console
Echo "\n";
Echo "d \t $date\n";
Echo "t \t $time\n";
Echo "v1 \t $inverterEnergyDayTotal\n";
Echo "v2 \t $inverterPowerLive\n";
Echo "v3 \t $consumptionEnergyDayTotal\n";
Echo "v4 \t $consumptionPowerLive\n";
Echo "v6 \t $inverterVoltageLive\n";
Echo "v7 \t ${$v7}\n";
Echo "v8 \t ${$v8}\n";
Echo "v9 \t ${$v9}\n";
Echo "v10 \t ${$v10}\n";
Echo "v11 \t ${$v11}\n";
Print "v12 \t ${$v12}\n";
Echo "\n";
Echo "Sending data to PVOutput.org \n";
Echo "$pvOutputURL \n";
Echo "\n";




} else {
echo "No smart meter found\n";

// Push to PVOutput
$pvOutputURL = $pvOutputApiURL
                . "key=" .  $pvOutputApiKEY
                . "&sid=" . $pvOutputSID
                . "&d=" .   $date
                . "&t=" .   $time
                . "&v1=" .  $inverterEnergyDayTotal
                . "&v2=" .  $inverterPowerLive
                . "&v6=" .  $inverterVoltageLive
                . "&v7=" .  $$v7
                . "&v8=" .  $$v8
                . "&v9=" .  $$v9
                . "&v10=" . $$v10
                . "&v11=" . $$v11
                . "&v12=" . $$v12;
file_get_contents(trim($pvOutputURL));													


//Print Values to Console
Echo "\n";
Echo "d \t $date\n";
Echo "t \t $time\n";
Echo "v1 \t $inverterEnergyDayTotal\n";
Echo "v2 \t $inverterPowerLive\n";
Echo "v6 \t $inverterVoltageLive\n";
Echo "v7 \t ${$v7}\n";
Echo "v8 \t ${$v8}\n";
Echo "v9 \t ${$v9}\n";
Echo "v10 \t ${$v10}\n";
Echo "v11 \t ${$v11}\n";
Print "v12 \t ${$v12}\n";
Echo "\n";
Echo "Sending data to PVOutput.org \n";
Echo "$pvOutputURL \n";
Echo "\n";

}

															


// Update data file with new EOD totals
if ($system_time > strtotime('Today 11:55pm') && $system_time < strtotime('Today 11:59pm')) {
$saveData = serialize(array('import' => $meterImportTotal, 'export' => $meterExportTotal));
file_put_contents($dataFile, $saveData);
}

?>
