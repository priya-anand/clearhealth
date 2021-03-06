/*****************************************************************************
*       PatientSelectAutoComplete.js
*
*       Author:  ClearHealth Inc. (www.clear-health.com)        2009
*       
*       ClearHealth(TM), HealthCloud(TM), WebVista(TM) and their 
*       respective logos, icons, and terms are registered trademarks 
*       of ClearHealth Inc.
*
*       Though this software is open source you MAY NOT use our 
*       trademarks, graphics, logos and icons without explicit permission. 
*       Derivitive works MUST NOT be primarily identified using our 
*       trademarks, though statements such as "Based on ClearHealth(TM) 
*       Technology" or "incoporating ClearHealth(TM) source code" 
*       are permissible.
*
*       This file is licensed under the GPL V3, you can find
*       a copy of that license by visiting:
*       http://www.fsf.org/licensing/licenses/gpl.html
*       
*****************************************************************************/
dojo.provide("custom.PatientSelectAutoComplete");
dojo.declare("custom.PatientSelectAutoComplete", dojox.data.QueryReadStore, {
	fetch:function (request) {
        	request.serverQuery = { patientSelect: request.query.name };
        	return this.inherited("fetch", arguments);
    	}
});

dojo.provide("custom.PatientSelectComboBox");
dojo.declare("custom.PatientSelectComboBox", dijit.form.ComboBox, {
	searchDelay: 500
});

