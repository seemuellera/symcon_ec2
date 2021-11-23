<?php

// Load AWS SDK
require 'aws.phar';
use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;

// Klassendefinition
class EC2 extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","EC2");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyString("AWSAccessKeyId","XXXXXXXX");
		$this->RegisterPropertyString("AWSSecretAccessKey","YYYYYYY");
		$this->RegisterPropertyString("EC2InstanceId","i-xxxxxxxxxxxxx");
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		
		//Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'EC2_RefreshInformation($_IPS[\'TARGET\']);');
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
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "EC2InstanceId", "caption" => "EC2 instance ID");
				
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'EC2_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Start Instance", "onClick" => 'EC2_Start($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Stop Instance", "onClick" => 'EC2_Stop($id);');
		
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
				if ($Value) {
					
					$this->Start();
				}
				else {
					
					$this->Stop();
				}
				
				sleep(3);
				$this->RefreshInformation();
				
				break;
			default:
				$this->LogMessage("An undefined compare mode was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		
		$ec2Client = new Ec2Client([
			'version'     => 'latest',
			'region'      => 'eu-central-1',
			'credentials' => [
				'key'    => $this->ReadPropertyString('AWSAccessKeyId'),
				'secret' => $this->ReadPropertyString('AWSSecretAccessKey'),
			],
		]);
		
		$ec2InstanceStatusInformation = $ec2Client->DescribeInstanceStatus([
			'InstanceIds' => [$this->ReadPropertyString('EC2InstanceId')]
		]);
		
		print_r($ec2InstanceStatusInformation);
		
		if ( count($ec2InstanceStatusInformation->'data:Aws\Result:private'['InstanceStatuses']) == 0 ) {
			
			SetValue($this->GetIDForIdent("Status"), false);
			return;
		}
		
		$ec2InstanceState = $ec2InstanceStatusInformation['data:Aws\Result:private']['InstanceStatuses'][0]['InstanceState']['Name'];
		
		$ec2RunningStates = Array("running","stopping","shutting-down","pending");
		
		if (in_array($ec2InstanceState, $ec2RunningStates) ) {
			
			SetValue($this->GetIDForIdent("Status"), true);
		};
	}
	
	public function Stop() {
		
		$this->LogMessage("Shutting down EC2 Instance", "DEBUG");
		
		$ec2Client = new Ec2Client([
			'version'     => 'latest',
			'region'      => 'eu-central-1',
			'credentials' => [
				'key'    => $this->ReadPropertyString('AWSAccessKeyId'),
				'secret' => $this->ReadPropertyString('AWSSecretAccessKey'),
			],
		]);
		
		$ec2InstanceStopResult = $ec2Client->StopInstances([
			'InstanceIds' => [$this->ReadPropertyString('EC2InstanceId')]
		]);

	}
	
	public function Start() {
		
		$this->LogMessage("Shutting down EC2 Instance", "DEBUG");
		
		$ec2Client = new Ec2Client([
			'version'     => 'latest',
			'region'      => 'eu-central-1',
			'credentials' => [
				'key'    => $this->ReadPropertyString('AWSAccessKeyId'),
				'secret' => $this->ReadPropertyString('AWSSecretAccessKey'),
			],
		]);
		
		$ec2InstanceStartResult = $ec2Client->StartInstances([
			'InstanceIds' => [$this->ReadPropertyString('EC2InstanceId')]
		]);
		
	}
}
