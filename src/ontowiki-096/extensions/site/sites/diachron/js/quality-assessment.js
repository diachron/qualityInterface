/**
 * Quality Assessment module. Provides the functionalities necessary to invoke the quality assessment process
 * via the REST API. Implemented by following the JavaScript module pattern.
 * Requires:
 * 		- JQuery.
 */
var qualityAsmEngine = (function() {
	
	// Private attributes, implemented by mean of closures
	var QUALITY_API_URL = "http://localhost/ontowiki/service/qualityproxy";
	
	// Module instance, public attributes and methods
	var thisObj = {};

	// ----------------------- Public Methods --------------------------------
	
	// Setup and start the quality assessment process, which is run asynchronously upon all datasets 
	// specified in arrDataSetURIs. Returns an object with two properties: 
	// outcome = Result of the process (ERROR, VALIDATION_ERROR, OK), 
	// data = Object containing information about the result of the operation (string with the error message in case of error)
	thisObj.launchQualityAssessment = function(arrDataSetURIs, arrMetricURIs, boolGenReport) {

		// Validate that all data has been provided
		if(!arrDataSetURIs || !arrMetricURIs || !(arrDataSetURIs instanceof Array) || !(arrMetricURIs instanceof Array) || 
				arrDataSetURIs.length <= 0 || arrMetricURIs.length <= 0) {
			return { outcome: "VALIDATION_ERROR", message: "Invalid argument" };
		}
		
		// Build JSON-LD specifying the list of metrics to be computed
		var strMetricsConfig = "[";
		
		for(var m = 0; m < arrMetricURIs.length; m++) {
			strMetricsConfig += "{\"@value\":\"" + arrMetricURIs[m] + "\"}";
			strMetricsConfig += ((m+1) < arrMetricURIs.length)?(","):("");
		}
		strMetricsConfig += "]";

		// Build and issue a Quality assessment request per dataset
		var arrDataSetsStatus = new Array();
		
		// Build a set containing the datasets to be processed and stating whether they have been
		for(var di = 0; di < arrDataSetURIs.length; di++) {
			arrDataSetsStatus.push({ dataSetURI: arrDataSetURIs[di], processed: false });
		}
		
		for(var d = 0; d < arrDataSetURIs.length; d++) {

			var paramDataset = arrDataSetURIs[d];
			var paramQualityReportRequired = boolGenReport || false;
			var paramMetricConfigId = generateUUID();
			var paramMetricsConfiguration = "{ " +
					"\"@id\":\"_:" + paramMetricConfigId + "\", " +
					"\"@type\":[\"http://github.com/EIS-Bonn/Luzzu#MetricConfiguration\"], " +
					"\"http://github.com/EIS-Bonn/Luzzu#metric\": " + strMetricsConfig +
				" }";
			
			thisObj.onBeginDataSetProcessing(paramDataset, paramMetricConfigId);
			
			// Issue the quality assessment request through a self-calling function, as we want provide the event handlers of the 
			// Ajax request with access to the variables paramDataSet and paramMetricConfigId, as these values change on every
			// iteration of the for cycle above and the Ajax request are sent asynchronously, it is necessary to pass the current 
			// values as parameters so that each invocation gets access to its corresponding values of the aforesaid parameters 
			sendQualityAssessmentRequest(paramDataset, paramQualityReportRequired, paramMetricConfigId, paramMetricsConfiguration, arrDataSetsStatus);
		}
		
		// Quality assessment process successfully launched and running	
		return { outcome: "OK", message: "Quality assessment process running" };
	};
	
	// ----------------------- Event Handlers --------------------------------
	
	thisObj.onBeginDataSetProcessing = function(dataSetURI, metricConfigId) { return; };	
	thisObj.onDataSetProcessed = function(dataSetURI, metricConfigId, result) { return; };	
	thisObj.onProcessError = function(dataSetURI, metricConfigId, error) { return; };	
	thisObj.onProcessCompleted = function(result) { return; };
	
	// ----------------------- Private Methods --------------------------------
	
	// Invokes the Quality Assessment service through an Ajax call.
	// The parameter arrDataSetStatus must be an array of objects containing the processing status of all the datasets to be assessed
	function sendQualityAssessmentRequest(paramDataset, paramQualityReportRequired, paramMetricConfigId, paramMetricsConfiguration, arrDataSetsStatus) { 
		
		// Issue REST API call (Notice that all these are asynchronous calls!)
		jQuery.ajax(QUALITY_API_URL, {
			type: "POST",
			contentType: "application/x-www-form-urlencoded; charset=UTF-8",
			dataType: "json",
			async: true,
			cache: false,
			data: { 
				Dataset: paramDataset,
				QualityReportRequired: paramQualityReportRequired,
				MetricsConfiguration: paramMetricsConfiguration
			},
			success: function(resData, jqXHR, status) {
				if(resData != null) {
					if(resData["Outcome"] === "SUCCESS") {
						thisObj.onDataSetProcessed(paramDataset, paramMetricConfigId, { resultData: resData, qualityReport: null });
					} else {
						thisObj.onProcessError(paramDataset, paramMetricConfigId, resData["ErrorMessage"]);
					}
				} else {
					thisObj.onProcessError(paramDataset, paramMetricConfigId, "Empty server response");
				}
			},
			error: function(jqXHR, errorMsg, errorThrown) {
				thisObj.onProcessError(paramDataset, errorMsg);
			},
			complete: function(jqXHR, textStatus) {
				// Set current dataset as processed and check if all datasets have been processed...
				var allCompleted = true;
				for(var d = 0; d < arrDataSetsStatus.length; d++) {
					// Set just processed dataset as completed
					if(arrDataSetsStatus[d].dataSetURI === paramDataset) {
						arrDataSetsStatus[d].processed = true;
					}
					// If at least one dataset has not yet been process, process is not completed yet
					if(!arrDataSetsStatus[d].processed) {
						allCompleted = false;
					}
				}
				
				// All datasets have been processed? then trigger event onProcessCompleted
				if(allCompleted) {
					thisObj.onProcessCompleted(arrDataSetsStatus);
				}
			},
			timeout: 1800000
		});
	};
		
	// GUUID generator. RFC4122 version 4 compliant solution that solves issues due to poor implementations of Math.random(), 
	// by offsetting the first 13 hex numbers by a hex portion of the timestamp. That way, even if Math.random is on 
	// the same seed, both clients would have to generate the UUID at the exact same millisecond (or 10,000+ years later) 
	// to get the same UUID. Source: http://stackoverflow.com/questions/105034/how-to-create-a-guid-uuid-in-javascript
	function generateUUID() {
		
	    var d = new Date().getTime();
	    
	    var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
	        var r = (d + Math.random()*16)%16 | 0;
	        d = Math.floor(d/16);
	        return (c=='x' ? r : (r&0x7|0x8)).toString(16);
	    });
	    
	    return uuid;
	};

	return thisObj;

})();