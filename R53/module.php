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
			
			$this->LogMessage("Unable to retrieve the DNS record sets", "ERROR");
			return false;
		}
		
		foreach ($records as $record) {
			
			if ($record['Name'] == $this->ReadPropertyString('RecordName') ) {
				
				print_r($record);
			}
		}
		
		// print_r($recordInformation->getPath('ResourceRecordSets/0/Name'));
		// nprint_r($recordInformation->getPath('ResourceRecordSets/0/ResourceRecords/0/Value'));
		
		
		/*
		if ( count($ec2InstanceStatusInformation->getPath('InstanceStatuses')) == 0 ) {
			
			SetValue($this->GetIDForIdent("Status"), false);
			return;
		}
		
		$ec2InstanceStateResponse = $ec2InstanceStatusInformation->getPath('InstanceStatuses');
		$ec2InstanceState = $ec2InstanceStateResponse[0]['InstanceState']['Name'];
		
		$ec2RunningStates = Array("running","stopping","shutting-down","pending");
		
		if (in_array($ec2InstanceState, $ec2RunningStates) ) {
			
			SetValue($this->GetIDForIdent("Status"), true);
		};
		*/
	}
	
}
