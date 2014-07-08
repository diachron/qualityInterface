/**
 * Data access object providing access to the Linked Open Data repository provided by OntoWiki.
 * Implemented by following the JavaScript module pattern.
 * Requires:
 * 		- JQuery.
 */
var lodDataAccess = (function() {

	// Module instance, public attributes and methods
	var thisObj = {};
	
	// Retrieve the set of Categories of Quality Metrics. Returns an array of objects, containing the results of the query. 
	// Each element representing a quality category: [ {uri:"uri1", name:"cat1"}, ..., {uri:"uri2", name: "cat2"} ]
	thisObj.selectCategories = function() {
		var sparqlQuery = "PREFIX dqm: <http://www.diachron-fp7.eu/dqm#>\n" + 
				"PREFIX daq: <http://purl.org/eis/vocab/daq#>\n" +
				"SELECT DISTINCT ?category_name " + 
				"FROM <http://www.diachron-fp7.eu/dqm#> WHERE " +
				"{ ?category_name rdfs:subClassOf daq:Category } ";
		
		var arrResultBinds = runSparqlQuery("http://www.diachron-fp7.eu/dqm#", sparqlQuery);
		// Array to collect the results of the query, will be also the return value of this method
		var queryResults = new Array();
		
		if(arrResultBinds != null) {
			// Extract variable values and set them as properties of each resulting category instance
			var curResCategory = null;
			var curCategoryURI = null;
			var curCategoryName = null;
			
			for(var i = 0; i < arrResultBinds.length; i++) {
				// Take the name of the category and remove the namespace prefix
				curCategoryURI = arrResultBinds[i]["category_name"].value || "";
				curCategoryName = curCategoryURI.substring(curCategoryURI.lastIndexOf("dqm#") + 4, curCategoryURI.length);
				
				curResCategory = { uri: curCategoryURI, name: curCategoryName };
				queryResults.push(curResCategory);
			}
		}

		return queryResults;
	};

	// Private attributes and methods, implemented by means of closures
	
	var DATASOURCE_URL = "../queries/editor";
	
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
	
	// Catch and handle errors, particularly those caused by exceptions (should in turn, invoke a 
	// method provided by the UI, that properly displays or logs error messages)
	var errorHandler = function(errorMsg, exception) {
		alert("ERROR: " + errorMsg + " (Exception: " + exception + ")"); 
	};
	
	// Return module singlenton (view module pattern)
	return thisObj;

}());