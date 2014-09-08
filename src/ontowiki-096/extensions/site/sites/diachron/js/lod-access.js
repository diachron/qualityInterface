/**
 * Data access object providing access to the Linked Open Data repository provided by OntoWiki.
 * Implemented by following the JavaScript module pattern.
 * Requires:
 * 		- JQuery.
 */
var lodDataAccess = (function() {
	
	// Private attributes, implemented by mean of closures
	var LOGIN_URL = "../application/login";
	var DATASOURCE_URL = "../queries/editor";
	var DQM_BASE_URI = "http://www.diachron-fp7.eu/dqm#";
	var DAQ_BASE_URI = "http://purl.org/eis/vocab/daq#";
	var CUBE_BASE_URI = "http://purl.org/linked-data/cube#";
	var DC_ELEMENTS_URI = "http://purl.org/dc/elements/1.1/"

	// Module instance, public attributes and methods
	var thisObj = {};
	
	// Retrieve the set of Categories of Quality Metrics. Returns an array of objects, containing the results of the query. 
	// Each element representing a quality category: [ {uri:"uri1", name:"cat1"}, ..., {uri:"uri2", name: "cat2"} ]
	thisObj.selectCategories = function() {
		var sparqlQuery = "PREFIX dqm: <" + DQM_BASE_URI + ">\n" + 
				"PREFIX daq: <" + DAQ_BASE_URI + ">\n" +
				"SELECT DISTINCT ?category_uri " + 
				"FROM <" + DQM_BASE_URI + "> WHERE " +
				"{ ?category_uri rdfs:subClassOf daq:Category } " + 
				"LIMIT 1000";
		
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
			"ORDER BY ASC(?category_uri) ASC(?dimension_uri) ASC(?metric_uri) " +
			"LIMIT 1000";
		
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
								 metric_uri: curMetricURI, metric_name: extractNameFromURI(curMetricURI), metric_label: extractNameFromURI(curMetricURI) };
				
				queryResults.push(curResMetric);
			}
		}

		return queryResults;
	};
	
	// Retrieve the set of Quality Metrics that have been computed for at least one DataSet, including their respective Dimensions and Categories. 
	// Returns an array of objects, containing the results of the query 
	// Each element representing a quality metric: [ {category_uri:"..", category_name:"..", dimension_uri:"..", dimension_name:"..", metric_uri:"..", metric_name:".."} ...]
	thisObj.selectComputedMetrics = function() {
		var sparqlQuery = "PREFIX daq: <" + DAQ_BASE_URI + "> " +
			"PREFIX qb: <" + CUBE_BASE_URI + "> " + 
			"SELECT DISTINCT ?category_uri ?category_name ?dimension_uri ?dimension_name ?metric_uri ?metric_name " +
			"WHERE " +
			"{ " +
					"?metric_internal_id a ?metric_uri . " + 
					"?metric_uri rdfs:subClassOf daq:Metric ." + 
					"?has_dimension_metric rdfs:range ?metric_uri ." + 
					"?has_dimension_metric rdfs:domain ?dimension_uri ." + 
					"?has_category_dim rdfs:range ?dimension_uri ." + 
					"?has_category_dim rdfs:domain ?category_uri ." + 
					"OPTIONAL { ?category_uri rdfs:label ?category_name } ." + 
					"OPTIONAL { ?dimension_uri rdfs:label ?dimension_name } ." + 
					"OPTIONAL { ?metric_uri rdfs:label ?metric_name } ." + 
			"} " +
			"ORDER BY ASC(?category_name) ASC(?dimension_name) ASC(?metric_name) " + 
			"LIMIT 1000";
		
		var arrResultBinds = runSparqlQuery(null, sparqlQuery,  "all");
		// Array to collect the results of the query, will be also the return value of this method
		var queryResults = new Array();
		
		if(arrResultBinds != null) {
			// Extract variable values and set them as properties of each resulting category instance
			var curResMetric = null;
			var curCategoryURI = null;
			var curDimensionURI = null;
			var curMetricURI = null;
			var curCategoryName = null;
			var curDimensionName = null;
			var curMetricName = null;
			var curMetricLabel = null;

			for(var i = 0; i < arrResultBinds.length; i++) {
				// Take the name of the category and remove the namespace prefix
				curCategoryURI = arrResultBinds[i]["category_uri"].value || "";
				curDimensionURI = arrResultBinds[i]["dimension_uri"].value || "";
				curMetricURI = arrResultBinds[i]["metric_uri"].value || "";
				curCategoryName = arrResultBinds[i]["category_name"].value || extractNameFromURI(curCategoryURI);
				curDimensionName = arrResultBinds[i]["dimension_name"].value || extractNameFromURI(curDimensionURI);
				curMetricName = extractNameFromURI(curMetricURI);
				curMetricLabel = arrResultBinds[i]["metric_name"].value || extractNameFromURI(curMetricURI);
				
				curResMetric = { category_uri: curCategoryURI, category_name: curCategoryName,
								 dimension_uri: curDimensionURI, dimension_name: curDimensionName,
								 metric_uri: curMetricURI, metric_name: curMetricName, metric_label: curMetricLabel };
				
				queryResults.push(curResMetric);
			}
		}

		return queryResults;
	};
	
	// Retrieve the DataSets having a Quality Graph, for which the specified metric has been computed. The values computed for the metric are also provided as result.
	// Each element of the result set is as: [ {category_uri:"..", category_name:"..", dimension_uri:"..", dimension_name:"..", metric_uri:"..", metric_name:".."} ...]
	thisObj.selectDataSetsByMetric = function(metricUri) {
		var sparqlQuery = "PREFIX daq: <" + DAQ_BASE_URI + "> " +
			"PREFIX qb: <" + CUBE_BASE_URI + "> " + 
			"PREFIX dc: <" + DC_ELEMENTS_URI + "> " + 
			"SELECT DISTINCT ?data_set_uri ?dataset_label ?dataset_comment ?metric_uri ?date_computed ?metric_value " +
			"WHERE " +
			"{ " +
					"?data_set_uri a daq:QualityGraph ; " + 
					"	rdfs:label ?dataset_label ; " + 
					"	rdfs:comment ?dataset_comment . " + 
					"GRAPH ?data_set_uri { " + 
					"	?obs qb:dataSet ?data_set_uri ; " + 
					"		daq:metric ?metric_uid ; " +
					"		daq:value ?metric_value . " +
					"	OPTIONAL { ?obs dc:date ?date_computed } . "	+
					"	?metric_uid a ?metric_uri . }" + 
					"FILTER ( REGEX(?metric_uri, \"" + metricUri + "\") ) " + 
			"} " + 
			"ORDER BY ASC(?metric_value) " +
			"LIMIT 1000";
				
		var arrResultBinds = runSparqlQuery(null, sparqlQuery,  "all");
		// Object used as a map, to store the rankings of several revisions of a single dataset. Map key: dataset URI
		var mapDataSetRankings = {};
		
		if(arrResultBinds != null) {
			// Extract variable values and set them as properties of each resulting category instance
			var curDataSet = null;
			var curDataSetURI = null;
			var curDataSetLabel = null;
			var curCategoryComment = null;
			var curMetricURI = null;
			var curMetricValue = null;
			var curDateComputed = null;

			for(var i = 0; i < arrResultBinds.length; i++) {
				// Take the values of interest and remove namespace prefixes as required
				curDataSetURI = arrResultBinds[i]["data_set_uri"].value || "";
				curDataSetLabel = arrResultBinds[i]["dataset_label"].value || "";
				curCategoryComment = arrResultBinds[i]["dataset_comment"].value || "";
				curMetricURI = arrResultBinds[i]["metric_uri"].value || "";
				curMetricValue = arrResultBinds[i]["metric_value"].value || "";
				
				// Prevent any potential errors caused by incorrectly formatted dates
				try {
					curDateComputed = new Date(arrResultBinds[i]["date_computed"].value || 0);
				} catch(err) {
					// If any error occurrs, just set the computed date to the worst value possible (the 1970's)
					curDateComputed = new Date(0);
				}
				
				// Look for the current dataset in the map of previously processed datasets...
				curDataSet = mapDataSetRankings[curDataSetURI];
				
				// and if a rating for the dataset has already been set, update the rating if newer
				if(curDataSet) {
					// Check if the current rating is newer, if so, update the rating value of the dataset
					if(curDataSet.date_computed < curDateComputed) {
						curDataSet.date_computed = curDateComputed;
						curDataSet.metric_value = curMetricValue;
					}
				} else {
					// Add a new dataset to the map of processed datasets					
					mapDataSetRankings[curDataSetURI] = { dataset_uri: curDataSetURI, dataset_label: curDataSetLabel, 
							 dataset_comment: curCategoryComment, metric_uri: curMetricURI,
							 date_computed: curDateComputed, metric_value: curMetricValue };
				}
			}
		}

		// Array to collect the results of the query, will be also the return value of this method
		var queryResults = new Array();
		
		// Return the set of datasets as an array		
		for(var dataset in mapDataSetRankings) {
			// check if object contains a property 
			if(mapDataSetRankings.hasOwnProperty(dataset)) {
				queryResults.push(mapDataSetRankings[dataset]);
			}
		}
		
		return queryResults;
	};

	// Private methods
	
	// Runs the SPARQL query provided as parameter, over the kBase knowledgebase (optional, null if query should be run agains all knowledgebases).
	// The third parameter, target, is optional, if provided can be: "this" (run query against the specified knowledgebase) or 
	// "all" (run query agains all the existing knowledgebases) set to "this" by default. 
	// The query is performed via OntoWiki's Queries extension, by sending a synchronous HTTP POST to the DATASOURCE_URL. 
	var runSparqlQuery = function(kBase, sparqlQuery, target) {
		
		var results = null;
		
		jQuery.ajax(DATASOURCE_URL, {
				type: "POST",
				contentType: "application/x-www-form-urlencoded; charset=UTF-8",
				dataType: "json",
				async: false,
				cache: false,
				data: { 
					target: target || "this", 
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
	
	// Authenticate and initiate session in OntoWiki
	var logIntoOntowiki = function() {
		
		jQuery.ajax(LOGIN_URL, {
			type: "POST",
			contentType: "application/x-www-form-urlencoded; charset=UTF-8",
			async: false,
			data: { 
				logintype: "locallogin", 
    			password: "dba", 
    			username: "dba"
			},
			error: function(jqXHR, errorMsg, ex) {
				errorHandler(errorMsg, ex);
			},
		});
	}
	
	// Extracts the name of an element (resource) of the dqm ontology given its whole URI
	var extractNameFromURI = function(dqmElementURI) {
		return dqmElementURI.substring(dqmElementURI.lastIndexOf("dqm#") + 4, dqmElementURI.length);
	};
	
	// Catch and handle errors, particularly those caused by exceptions (should in turn, invoke a 
	// method provided by the UI, that properly displays or logs error messages)
	var errorHandler = function(errorMsg, exception) {
		alert("ERROR: " + errorMsg + " (Exception: " + exception + ")"); 
	};
	
	// Some queries require to be logged into OntoWiki. TODO: think about a better place to login, handle expired session
	logIntoOntowiki();
	
	// Return module singlenton (view module pattern)
	return thisObj;

}());