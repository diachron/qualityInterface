/**
 * Data access object providing access to the Linked Open Data repository provided by OntoWiki.
 * Implemented by following the JavaScript module pattern.
 * Requires:
 * 		- JQuery.
 */
var lodDataAccess = (function() {
	
	// Private attributes, implemented by mean of closures
	var DATASOURCE_URL = "../queries/editor";
	var DQM_BASE_URI = "http://www.diachron-fp7.eu/dqm#";
	var DAQ_BASE_URI = "http://purl.org/eis/vocab/daq#";

	// Module instance, public attributes and methods
	var thisObj = {};
	
	// Retrieve the set of Categories of Quality Metrics. Returns an array of objects, containing the results of the query. 
	// Each element representing a quality category: [ {uri:"uri1", name:"cat1"}, ..., {uri:"uri2", name: "cat2"} ]
	thisObj.selectCategories = function() {
		var sparqlQuery = "PREFIX dqm: <" + DQM_BASE_URI + ">\n" + 
				"PREFIX daq: <" + DAQ_BASE_URI + ">\n" +
				"SELECT DISTINCT ?category_uri " + 
				"FROM <" + DQM_BASE_URI + "> WHERE " +
				"{ ?category_uri rdfs:subClassOf daq:Category } ";
		
		var arrResultBinds = runSparqlQuery(DQM_BASE_URI, sparqlQuery);
		// Array to collect the results of the query, will be also the return value of this method
		var queryResults = new Array();
		
		if(arrResultBinds != null) {
			// Extract variable values and set them as properties of each resulting category instance
			var curResCategory = null;
			var curCategoryURI = null;
			
			for(var i = 0; i < arrResultBinds.length; i++) {
				// Take the name of the category and remove the namespace prefix
				curCategoryURI = arrResultBinds[i]["category_uri"].value || "";
				
				curResCategory = { uri: curCategoryURI, name: extractNameFromURI(curCategoryURI) };
				queryResults.push(curResCategory);
			}
		}

		return queryResults;
	};
	
	// Retrieve the set of Quality Metrics, including their respective Dimensions and Categories. 
	// Returns an array of objects, containing the results of the query. 
	// Each element representing a quality metric: [ {category_uri:"..", category_name:"..", dimension_uri:"..", dimension_name:"..", metric_uri:"..", metric_name:".."} ...]
	thisObj.selectMetrics = function() {
		var sparqlQuery = "PREFIX dqm: <" + DQM_BASE_URI + "> " +
			"PREFIX daq: <" + DAQ_BASE_URI + "> " +
			"SELECT ?category_uri ?dimension_uri ?metric_uri " +
			"WHERE " +
			"{ " +
					"?has_dimension_metric rdfs:subPropertyOf daq:hasMetric. " +
					"?has_dimension_metric rdfs:domain ?dimension_uri . " +
					"?has_dimension_metric rdfs:range ?metric_uri . " +
					"?has_category_dim rdfs:range ?dimension_uri . " +
					"?has_category_dim rdfs:subPropertyOf daq:hasDimension. " +
					"?has_category_dim rdfs:domain ?category_uri . " +
			"} " +
			"ORDER BY ASC(?category_uri) ASC(?dimension_uri) ASC(?metric_uri) ";
		
		var arrResultBinds = runSparqlQuery(DQM_BASE_URI, sparqlQuery);
		// Array to collect the results of the query, will be also the return value of this method
		var queryResults = new Array();
		
		if(arrResultBinds != null) {
			// Extract variable values and set them as properties of each resulting category instance
			var curResMetric = null;
			var curCategoryURI = null;
			var curDimensionURI = null;
			var curMetricURI = null;
						
			for(var i = 0; i < arrResultBinds.length; i++) {
				// Take the name of the category and remove the namespace prefix
				curCategoryURI = arrResultBinds[i]["category_uri"].value || "";
				curDimensionURI = arrResultBinds[i]["dimension_uri"].value || "";
				curMetricURI = arrResultBinds[i]["metric_uri"].value || "";
				
				curResMetric = { category_uri: curCategoryURI, category_name: extractNameFromURI(curCategoryURI),
								 dimension_uri: curDimensionURI, dimension_name: extractNameFromURI(curDimensionURI),
								 metric_uri: curMetricURI, metric_name: extractNameFromURI(curMetricURI) };
				
				queryResults.push(curResMetric);
			}
		}

		return queryResults;
	};

	// Private methods
	
	// Runs the SPARQL query provided as parameter, over the kBase knowledgebase. The query is performed  
	// via OntoWiki's Queries extension, by sending a synchronous HTTP POST to the DATASOURCE_URL. 
	var runSparqlQuery = function(kBase, sparqlQuery) {
		
		var results = null;
		
		jQuery.ajax(DATASOURCE_URL, {
				type: "POST",
				contentType: "application/x-www-form-urlencoded; charset=UTF-8",
				accepts: "application/json",
				dataType: "json",
				async: false,
				cache: false,
				data: { 
					target: "this", 
					query: sparqlQuery, 
					result_format: "json", 
					result_outputfile: "true",
					m: kBase
				},
				success: function(resData, jqXHR, status) {
					if(resData != null && resData.results != null && resData.results.bindings != undefined) {
						// Retrieve the variables bindings in order to return them as result
						results = resData.results.bindings;
					}
				},
				error: function(jqXHR, errorMsg, ex) {
					errorHandler(errorMsg, ex);
				},
				timeout: 30000
			});
		
		return results;
	};
	
	// Extracts the name of an element (resource) of the dqm ontology given its whole URI
	var extractNameFromURI = function(dqmElementURI) {
		return dqmElementURI.substring(dqmElementURI.lastIndexOf("dqm#") + 4, dqmElementURI.length);
	};
	
	// Catch and handle errors, particularly those caused by exceptions (should in turn, invoke a 
	// method provided by the UI, that properly displays or logs error messages)
	var errorHandler = function(errorMsg, exception) {
		alert("ERROR: " + errorMsg + " (Exception: " + exception + ")"); 
	};
	
	// Return module singlenton (view module pattern)
	return thisObj;

}());