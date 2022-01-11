<?php

// Load AWS SDK
require_once __DIR__ .  '/aws.phar';
use Aws\Route53\Route53Client;
use Aws\Exception\AwsException;

// Klassendefinition
class R53 extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","R53");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyString("AWSAccessKeyId","XXXXXXXX");
		$this->RegisterPropertyString("AWSSecretAccessKey","YYYYYYY");
		$this->RegisterPropertyString("HostedZoneId","xxxxxxxxxxxxx");
		$this->RegisterPropertyString("RecordType","A,PTR,...");
		$this->RegisterPropertyString("RecordName","yyyyyyyyyy");
		
		// Variables
		$this->RegisterVariableString("RecordValue","Record Value");
		$this->RegisterVariableString("RequestedValue","Requested Value");
		$this->RegisterVariableBoolean("InSync", "In Sync", "~Alert.Reversed");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'R53_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "AWSAccessKeyId", "caption" => "AWS Access Key ID");
		$form['elements'][] = Array("type" => "PasswordTextBox", "name" => "AWSSecretAccessKey", "caption" => "AWS Secret Access Key");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "HostedZoneId", "caption" => "Hosted Zone ID");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "RecordType", "caption" => "Record Type");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "RecordName", "caption" => "Record Name");
				
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'R53_RefreshInformation($id);');
		
		// Return the completed form
		return json_encode($form);

	}
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				
				break;
			default:
				$this->LogMessage("An undefined compare mode was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		
		$route53Client = new Route53Client([
			'version'     => 'latest',
			'region'      => 'eu-central-1',
			'credentials' => [
				'key'    => $this->ReadPropertyString('AWSAccessKeyId'),
				'secret' => $this->ReadPropertyString('AWSSecretAccessKey'),
			],
		]);
		
		$recordInformation = $route53Client->listResourceRecordSets([
			'HostedZoneId' => $this->ReadPropertyString('HostedZoneId'),
			'StartRecordType' => $this->ReadPropertyString('RecordType'),
			'StartRecordName' => $this->ReadPropertyString('RecordName')
		]);
		
		$records = $recordInformation->getPath('ResourceRecordSets');
		
		if (count($records) == 0) {
			
			$this->LogMessage("Unable to retrieve the DNS record sets", "CRIT");
			return false;
		}
		
		$recordValue = "";
		
		foreach ($records as $record) {
			
			if ($record['Name'] == $this->ReadPropertyString('RecordName') ) {
				
				if (count($record['ResourceRecords']) == 0) {
					
					$this->LogMessage("No Record value found", "CRIT");
					return false;
				}
				
				if (count($record['ResourceRecords']) > 1) {
					
					$this->LogMessage("Multi-Value record found", "CRIT");
					return false;
				}
				
				$recordValue = $record['ResourceRecords'][0]['Value'];
			}
		}
		
		if ($recordValue == "") {
			
			$this->LogMessage("No Record Set with the given name was found","CRIT");
			return false;
		}
		
		SetValue($this->GetIDForIdent("RecordValue"), $recordValue);
		
		if (GetValue($this->GetIDForIdent("RecordValue")) == GetValue($this->GetIDForIdent("RequestedValue")) ) {
			
			SetValue($this->GetIDForIdent("InSync"), true);
		}
		else {
			
			SetValue($this->GetIDForIdent("InSync"), false);
		}
	}
	
	public function UpdateRecord(String $newValue, Int $newTTL) {
		
		
		$this->LogMessage("Updating DNS record to new value: $newValue", "INFO");
		
		$route53Client = new Route53Client([
			'version'     => 'latest',
			'region'      => 'eu-central-1',
			'credentials' => [
				'key'    => $this->ReadPropertyString('AWSAccessKeyId'),
				'secret' => $this->ReadPropertyString('AWSSecretAccessKey'),
			],
		]);
		
		$recordUpdateResult = $route53Client->changeResourceRecordSets([
			'ChangeBatch' => [
				'Changes' => [
					[
						'Action'	=> 'UPSERT',
						'ResourceRecordSet'	=> [
							'Name' => $this->ReadPropertyString('RecordName'), 
							'ResourceRecords' => [
								[
									'Value' => $newValue,
								],
							],
							'TTL' => $newTTL,
							'Type' => $this->ReadPropertyString('RecordType'),
						],
					],
				],
			],
			'HostedZoneId' => $this->ReadPropertyString('HostedZoneId'),
		]);
		
		SetValue($this->GetIDForIdent("RequestedValue"), $newValue);
		
		IPS_Sleep(500);
		
		$this->RefreshInformation();
	}
}
