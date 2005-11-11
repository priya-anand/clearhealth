<?php
$loader->requireOnce('controllers/C_Coding.class.php');
$loader->requireOnce('freeb2/local/controllers/C_FreeBGateway.class.php');
$loader->requireOnce('local/includes/freebGateway/CHToFBArrayAdapter.class.php');

/**
 * A patient Encounter
 */
class C_Encounter extends Controller {
	var $coding;
	var $coding_parent_id = 0;
	var $encounter_date_id = 0;
	var $encounter_value_id = 0;
	var $encounter_person_id = 0;
	var $payment_id = 0;

	function C_Encounter() {
		$this->controller();
		$this->coding = new C_Coding();
	}

	function actionAdd() {
		return $this->actionEdit();
	}

	/**
	 * Edit/Add an encounter
	 */
	function actionEdit($encounter_id = 0) {
		if (isset($this->encounter_id)) {
			$encounter_id = $this->encounter_id;
		}
		
		$encounter_id = $this->_enforcer->int($encounter_id);
		
		// This is being passed in as "occurence_id" from the Calendar, but was
		// originally coded in this method as "appointment_id"..
		$appointment_id = $this->GET->getTyped('occurence_id', 'int');
		$patient_id = $this->GET->getTyped('patient_id', 'int');
		
		$valid_appointment_id = false;

		// check if an encounter_id already exists for this appointment
		if ($appointment_id > 0) {
			$valid_appointment_id = true;
			ORDataObject::factory_include('Encounter');
			$id = Encounter::encounterIdFromAppointmentId($appointment_id);
			if ($id > 0) {
				$encounter_id         = $id;
				$valid_appointment_id = false;
			} 
		}

		if ($encounter_id > 0) {
			$this->set('encounter_id',$encounter_id);
			$this->set('external_id', $this->get('encounter_id'));
		}
		if ($patient_id > 0) {
			$this->set('patient_id',$patient_id);
		}
		//if ($encounter_id == 0 && $this->get('encounter_id') > 0) {
		//	$encounter_id = $this->get('encounter_id');
		//}	
		$this->set('encounter_id',$encounter_id);
		$encounter =& Celini::newORDO('Encounter',array($encounter_id,$this->get('patient_id')));
		$person =& Celini::newORDO('Person');
		$building =& Celini::newORDO('Building');

		$encounterDate =& Celini::newORDO('EncounterDate',array($this->encounter_date_id,$encounter_id));
		$encounterDateGrid = new cGrid($encounterDate->encounterDateList($encounter_id));
		$encounterDateGrid->name = "encounterDateGrid";
		$encounterDateGrid->registerTemplate('date','<a href="'.Celini::Managerlink('editEncounterDate',$encounter_id).'id={$encounter_date_id}&process=true">{$date}</a>');
		$this->assign('NEW_ENCOUNTER_DATE',Celini::managerLink('editEncounterDate',$encounter_id)."id=0&process=true");

		$encounterValue =& Celini::newORDO('EncounterValue',array($this->encounter_value_id,$encounter_id));
		$encounterValueGrid = new cGrid($encounterValue->encounterValueList($encounter_id));
		$encounterValueGrid->name = "encounterValueGrid";
		$encounterValueGrid->registerTemplate('value','<a href="'.Celini::Managerlink('editEncounterValue',$encounter_id).'id={$encounter_value_id}&process=true">{$value}</a>');
		$this->assign('NEW_ENCOUNTER_VALUE',Celini::managerLink('editEncounterValue',$encounter_id)."id=0&process=true");

		$encounterPerson =& Celini::newORDO('EncounterPerson',array($this->encounter_person_id,$encounter_id));
		$encounterPersonGrid = new cGrid($encounterPerson->encounterPersonList($encounter_id));
		$encounterPersonGrid->name = "encounterPersonGrid";
		$encounterPersonGrid->registerTemplate('person','<a href="'.Celini::Managerlink('editEncounterPerson',$encounter_id).'id={$encounter_person_id}&process=true">{$person}</a>');
		$this->assign('NEW_ENCOUNTER_PERSON',Celini::managerLink('editEncounterPerson',$encounter_id)."id=0&process=true");
		
		$payment =& Celini::newORDO('Payment',$this->payment_id);
		if ($payment->_populated == false) {
			$payment->set('title','Co-Pay');
		}
		$payment->set("encounter_id",$encounter_id);
		$paymentGrid = new cGrid($payment->paymentsFromEncounterId($encounter_id));
		$paymentGrid->name = "paymentGrid";
		$paymentGrid->registerTemplate('amount','<a href="'.Celini::Managerlink('editPayment',$encounter_id).'id={$payment_id}&process=true">{$amount}</a>');
		$paymentGrid->registerFilter('payment_date', array('DateObject', 'ISOToUSA'));
		$this->assign('NEW_ENCOUNTER_PAYMENT',Celini::managerLink('editPayment',$encounter_id)."id=0&process=true");

		
		$appointments = $encounter->appointmentList();
		$appointmentArray = array("" => " ");
		foreach($appointments as $appointment) {
			$appointmentArray[$appointment['occurence_id']] = date("m/d/Y H:i",strtotime($appointment['appointment_start'])) . " " . $appointment['building_name'] . "->" . $appointment['room_name'] . " " . $appointment['provider_name'];
		}
		
		
		// If this is a saved encounter, generate the following:
		if ($this->get('encounter_id') > 0) {
			// Load data that has been stored
			$formData =& Celini::newORDO("FormData");
			$formDataGrid =& new cGrid($formData->dataListByExternalId($encounter_id));
			$formDataGrid->name  = "formDataGrid";
			$formDataGrid->registerTemplate('name','<a href="'.Celini::link('data','Form').'id={$form_data_id}">{$name}</a>');
		// commenting this line out fixed 3602 
		//	$formDataGrid->pageSize = 10;
		// 	
			// Generate a menu of forms that are connected to Encounters
			$menu = Menu::getInstance();
			$connectedForms = $menu->getMenuData('patient',
				$menu->getMenuIdFromTitle('patient','Encounter Forms'));
			
			$formList = array();
			if (isset($connectedForms['forms'])) {
				foreach($connectedForms['forms'] as $form) {
					$formList[$form['form_id']] = $form['title'];
				}
			}
		}
		
		//if an appointment id is supplied the request is coming from the 
		//calendar and so prepopulate the defaults
		if ($appointment_id > 0 && $valid_appointment_id) {
			$encounter->set("occurence_id",$appointment_id);
			$encounter->set("patient_id",$this->get("patient_id"));
			if (isset($appointments[$appointment_id])) {
				$encounter->set("building_id",$appointments[$appointment_id]['building_id']);
			}
			if (isset($appointments[$appointment_id])) {
				$encounter->set("treating_person_id",$appointments[$appointment_id]['provider_id']);
			}
		}

		$insuredRelationship =& Celini::newORDO('InsuredRelationship');


		$this->assign_by_ref('insuredRelationship',$insuredRelationship);
		$this->assign_by_ref('encounter',$encounter);
		$this->assign_by_ref('person',$person);
		$this->assign_by_ref('building',$building);
		$this->assign_by_ref('encounterDate',$encounterDate);
		$this->assign_by_ref('encounterDateGrid',$encounterDateGrid);
		$this->assign_by_ref('encounterPerson',$encounterPerson);
		$this->assign_by_ref('encounterPersonGrid',$encounterPersonGrid);
		$this->assign_by_ref('encounterValue',$encounterValue);
		$this->assign_by_ref('encounterValueGrid',$encounterValueGrid);
		$this->assign_by_ref('payment',$payment);
		$this->assign_by_ref('paymentGrid',$paymentGrid);
		$this->assign_by_ref('appointmentList',$appointments);
		$this->assign_by_ref('appointmentArray',$appointmentArray);
		
		$this->assign('FORM_ACTION',Celini::link('edit',true,true,$encounter_id));
		$this->assign('FORM_FILLOUT_ACTION',Celini::link('fillout','Form'));

		if ($encounter_id > 0) {
			$this->coding->assign('FORM_ACTION',Celini::link('edit',true,true,$encounter_id));
			$this->coding->assign("encounter", $encounter);
			$codingHtml = $this->coding->update_action_edit($encounter_id,$this->coding_parent_id);
			$this->assign('codingHtml',$codingHtml);
			$this->assign_by_ref('formDataGrid',$formDataGrid);
			$this->assign_by_ref('formList',$formList);
		}

		if ($encounter->get('status') === "closed") {
			ORDataObject::factory_include('ClearhealthClaim');
			$claim =& ClearhealthClaim::fromEncounterId($encounter_id);
			//printf('<pre>%s</pre>', var_export($claim->toArray(), true));
			$this->assign('FREEB_ACTION',$GLOBALS['C_ALL']['freeb2_dir'] . substr(Celini::link('list_revisions','Claim','freeb2',$claim->get('identifier'),false,false),1));
			$this->assign('PAYMENT_ACTION',Celini::link('payment','Eob',true,$claim->get('id')));

			$this->assign('encounter_has_claim',false);
			if ($claim->_populated) {
				$this->assign('encounter_has_claim',true);
			}

			$this->assign('REOPEN_ACTION',Celini::link('reopen', true, true, $encounter->get('id')) . 'process=true');
		}
		else {
			ORdataObject::factory_include('ClearhealthClaim');
			$claim =& ClearhealthClaim::fromEncounterId($encounter_id);
			if ($claim->get('identifier') > 0) {
				$this->assign('claimSubmitValue', 'rebill');
			}
			else {
				$this->assign('claimSubmitValue', 'close');
			}
		}
		return $this->view->render("edit.html");
	}

	
	function processEdit($encounter_id=0) {
		if (isset($_POST['saveCode'])) {
			$this->coding->update_action_process();
			return;
		}


		$encounter =& Celini::newORDO('Encounter',array($encounter_id,$this->get('patient_id')));
		$encounter->populate_array($_POST['encounter']);

		if (isset($_POST['select_payer'])) {
			$encounter->persist();
			return;
		}
		
				
		$encounter->persist();
		$this->encounter_id = $encounter->get('id');

		if (isset($_POST['encounterDate']) && !empty($_POST['encounterDate']['date'])) {
			$this->encounter_date_id = $_POST['encounterDate']['encounter_date_id'];
			$encounterDate =& Celini::newORDO('EncounterDate',array($this->encounter_date_id,$this->encounter_id));
			$encounterDate->populate_array($_POST['encounterDate']);
			$encounterDate->persist();
			$this->encounter_date_id = $encounterDate->get('id');
		}
		if (isset($_POST['encounterValue']) && !empty($_POST['encounterValue']['value'])) {
			$this->encounter_value_id = $_POST['encounterValue']['encounter_value_id'];
			$encounterValue =& Celini::newORDO('EncounterValue',array($this->encounter_value_id,$this->encounter_id));
			$encounterValue->populate_array($_POST['encounterValue']);
			$encounterValue->persist();
			$this->encounter_value_id = $encounterValue->get('id');
		}
		if (isset($_POST['encounterPerson']) && !empty($_POST['encounterPerson']['person_id'])) {
			$this->encounter_person_id = $_POST['encounterPerson']['encounter_person_id'];
			$encounterPerson =& Celini::newORDO('EncounterPerson',array($this->encounter_person_id,$this->encounter_id));
			$encounterPerson->populate_array($_POST['encounterPerson']);
			$encounterPerson->persist();
			$this->encounter_person_id = $encounterPerson->get('id');
		}
		if (isset($_POST['payment']) && !empty($_POST['payment']['amount'])) {
			$this->payment_id = $_POST['payment']['payment_id'];
			$payment =& Celini::newORDO('Payment',$this->payment_id);
			$payment->set("encounter_id", $this->encounter_id);
			$payment->populate_array($_POST['payment']);
			$payment->persist();
			$this->payment_id = $payment->get('id');
		}

		if (isset($_POST['encounter']['close'])) {
			$patient =& Celini::newORDO('Patient',$encounter->get('patient_id'));
			ORDataObject::Factory_include('InsuredRelationship');
			$relationships = InsuredRelationship::fromPersonId($patient->get('id'));

			if ($relationships == null) { 
				$this->messages->addMessage("This Patient has no Insurance Information, please add insurance information and try again <br>");
				return;
			}else{	
				$encounter->set("status","closed");	
				$encounter->persist();
				$this->_generateClaim($encounter);
			}
		}
		
		// If this is a rebill, pass it off to the rebill method
		if (isset($_POST['encounter']['rebill'])) {
			$encounter->set('status', 'closed');
			$encounter->persist();
			$this->_handleRebill($encounter);
		}
	}

	/**
	 * Re-opens a claim and redirects back to the encounter view
	 *
	 * @param int
	 */
	function processReopen_edit($encounter_id) {
		$encounter =& Celini::newORDO('Encounter', $encounter_id);
		$encounter->set('status', 'open');
		$encounter->persist();
		
		// return display
		$this->_state = false;
		return $this->actionEdit($encounter->get('id'));
	}


	/**
	 * Rebill an claim
	 *
	 * This will be called by {@link processEdit()}
	 *
	 * @param  int
	 * @access private
	 */
	function _handleRebill(&$encounter) {
		$this->_sendClaim($encounter, 'rebill');
		// no need to return, as processEdit() will fall back to actionEdit()
	}

	


	/**
	 * Util function to check if we can rebill
	 *
	 * rule is: EOB payment has been made, There is an outstanding Balance, there is a secondary payer
	 */
	function _canRebill($encounterId) {
		$claim =& ClearhealthClaim::fromEncounterId($encounterId);

		ORDataObject::factory_include('Payment');
		$payments = Payment::fromForeignId($claim->get('id'));

		// check for EOB payment
		if (count($payments) > 0)  {

			$encounter =& Celini::newORDO('Encounter',$encounterId);

			// check for an outstanding balance
			$status = $claim->accountStatus($encounter->get('patient_id'),$encounterId);
			if ($status['total_balance'] > 0) {

				// check for a secondary payer

				ORDataObject::factory_include('InsuredRelationship');
				$payers = InsuredRelationship::fromPersonId($encounter->get('patient_id'));
				if (count($payers) > 1) {
					return true;
				}
			}
		}

		return false;
	}

	function update_action($foreign_id = 0, $parent_id = 0) {
		$this->coding_parent_id = $parent_id;
		return $this->encounter_action_edit($this->get('encounter_id'));
	}

	function _generateClaim(&$encounter,$claim = false) {
		$this->_sendClaim($encounter, 'new');
	}
	
	/**
	 * Handles the actual interaction with the gateway
	 *
	 * <i>$type</i> should always be "new" or "rebill"
	 *
	 * @param  Encounter
	 * @param  string
	 * @access private
	 */
	function _sendClaim(&$encounter, $type) {
		assert('$type == "new" || $type == "rebill"');
		// load gateway
		global $loader;
		$loader->requireOnce('local/includes/freebGateway/ClearhealthToFreebGateway.class.php');
		
		$gateway =& new ClearhealthToFreebGateway($this, $encounter);
		$gateway->send($type);
	}

	/**
	 * Deletes a claimline from the encounter
	 *
	 * This serves as an alias for {@link C_Coding::delete_claimline()}.
	 *
	 * @param  int
	 * @access protected
	 * @see    C_Coding::delete_claimline()
	 */
	function delete_claimline_action_process($claimline_id) {
		$encounter =& Celini::newORDO('Encounter', $this->GET->getTyped('encounter_id', 'int'));
		
		// double check to insure the encounter is open
		if($encounter->get('status') === "open") {
			$this->coding->delete_claimline($claimline_id);
		}
		
		// return display
		$this->_state = false;
		return $this->actionEdit($encounter->get('id'));
	}
}
?>
