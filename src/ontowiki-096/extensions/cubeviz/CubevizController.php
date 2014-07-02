<?php

/**
 * 
 */
class CubevizController extends OntoWiki_Controller_Component 
{
    protected $_configuration = null;
    protected $_titleHelperLimit = -1;
    
    public function init () 
    {
        parent::init();
        
        $loader = Zend_Loader_Autoloader::getInstance();
        $loader->registerNamespace('CubeViz_');
        $loader->registerNamespace('DataCube_');
        $path = __DIR__;
        set_include_path(
            get_include_path() . PATH_SEPARATOR . 
            $path . DIRECTORY_SEPARATOR .'classes' . DIRECTORY_SEPARATOR . PATH_SEPARATOR
        );
        
        // limit dimension element number
        $this->_dimensionElementLimit = 0 < (int) $this->_privateConfig->get('dimensionElementLimit')
            ? $this->_privateConfig->get('dimensionElementLimit')
            : 100;
        
        // max number of result entries to use title helper
        $this->_titleHelperLimit = 0 < (int) $this->_privateConfig->get('titleHelperLimit')
            ? $this->_privateConfig->get('titleHelperLimit')
            : 400;
    }
    
    /**
     *
     */
    public function analyzeAction() 
    {       
        // In case no model was selected or if analyze tool was deactivated, 
        // it redirects to the root url of OntoWiki
        if (false === isset($this->_owApp->selectedModel) 
            || false === $this->_privateConfig->get('useAnalyzeTool')) {
            $this->_helper->redirector->gotoUrl('/');
            return;
        }
         
        // set paths
        $basePath = $this->view->basePath = $this->_config->staticUrlBase . 'extensions/cubeviz/';
        $baseCssPath = $basePath .'public/css/';
        
        /**
         * Including css files for this action
         */
        $this->view->headLink()
            ->appendStylesheet($baseCssPath.'foreign/Bootstrap/bootstrap.min.css')
            ->appendStylesheet($baseCssPath.'/main.css')
            ->appendStylesheet($baseCssPath.'/AnalyzeAction/integrityConstraints.css');
            
        /**
         * Load model information
         */
        $model = $this->_owApp->selectedModel;
        $modelIri = $model->getModelIri();
        $modelStore = $model->getStore();
        
        $modelInformation = CubeViz_ViewHelper::getModelInformation($modelStore, $model, $modelIri);
        $modelInformation ['rdfs:label'] = true === isset($modelInformation ['rdfs:label'])
            ? $modelInformation ['rdfs:label']
            : $modelIri;
            
        
        $this->view->modelIri = $modelIri;
        $this->view->modelLabel = $modelInformation ['rdfs:label'];
        $this->view->translate = $this->_owApp->translate;
        $this->view->staticUrlBase = $this->_config->staticUrlBase;
        $this->view->cubevizImagesPath = $basePath .'public/images/';
    
        // fill title-field
        $this->view->placeholder('main.window.title')
                   ->set($modelInformation ['rdfs:label']);
        
        $on = $this->_owApp->getNavigation();
        $on->disableNavigation (); // disable OntoWiki's Navigation 
        
        
        /**
         * collect Datacube related information about the knowledge base
         */
        $query = new DataCube_Query(
            $model, 
            $this->_titleHelperLimit, 
            $this->_dimensionElementLimit
        );
        
        /**
         * Go through all queries which ask for general information about the DataCube
         */
        $this->view->generalInformation = array();
        foreach ($this->_privateConfig->get('AnalyzeActionConfig')->get('generalInformation') as $entry) 
        {
            if (true === isset($entry->query)) {
                
                $entry->result = count($model->sparqlQuery ($entry->query));
                $entry->encodedQuery = urlencode ($entry->query);
                
                $this->view->generalInformation [] = $entry;  
            }
        }
        
        /**
         * Go through all queries which ask for integrity constraints
         */
        $this->view->integrityConstraints = array ();
        
        $integrityConstraints = $this->_privateConfig->get('AnalyzeActionConfig')->get('integrityConstraints')->toArray();
        foreach ($integrityConstraints as $entry) 
        {
            if (true === isset($entry ['query'])) {
                
                try {
                    $entry ['result'] = $model->sparqlQuery ($entry ['query']);
                    
                    // no template was set
                    if (false == isset ($entry ['template']) 
                        || ( true == isset ($entry ['template']) && '' == $entry ['template'] )) {
                        if (true == isset($entry ['result'][0])) {
                            $entry ['result'] = $entry ['result'][0]['__ask_retval'];
                        } else {
                            $entry ['result'] = 0;
                        }
                        
                    // if there was a template defined
                    } else {
                        $entry ['result'] = 0;
                        
                        // check for each entry got by query above if the filled
                        // template query return something
                        if (0 != $entry ['result']) {
                            foreach ($entry ['result'] as $resEle) {
                                $sparql = str_replace ('$P', $resEle ['element'], $entry ['template']);
                                $result = $model->sparqlQuery ($sparql);
                                if (true == isset($result[0])) {
                                    $entry ['result'] = $result[0]['__ask_retval'];
                                    break;
                                }
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    // something went wrong with Erfurt
                    $entry ['result'] = 1;
                }
                
                $entry ['hasError'] = '1' == $entry ['result'];
                
                $entry ['encodedQuery'] = urlencode ($entry ['query']);
                
                $this->view->integrityConstraints [] = $entry;  
            }
        }
                
        /**
         * Go through all datasets and set related information
         */
        $this->view->dataSets = array ();
        
        if (true === $this->_privateConfig->get('showAnalyzeToolDataSets')) {
            
            $dataStructureDefinitions = $query->getDataStructureDefinitions();
            $tmp = $query->getDataSets();
        
            foreach ($tmp as $dataSet) {
                
                // data structure definitions
                foreach ($dataStructureDefinitions as $ds) {
                    if ($ds['__cv_uri'] == $dataSet[DataCube_UriOf::Structure]) {
                        $dataSet ['dataStructureDefinition'] = $ds;
                    }
                }
                
                // attributes
                $attributes = $query->getComponents(
                    $dataSet ['dataStructureDefinition']['__cv_uri'], $dataSet['__cv_uri'],
                    DataCube_UriOf::Attribute
                );
                
                $dataSet['attributes'] = array ();            
                foreach ($attributes as $attribute) {
                    $dataSet['attributes'] [] = $attribute;
                }
                
                // measures
                $measures = $query->getComponents(
                    $dataSet ['dataStructureDefinition']['__cv_uri'], $dataSet['__cv_uri'],
                    DataCube_UriOf::Measure
                );
                
                $dataSet['measures'] = array();
                foreach ($measures as $measure) {
                    $dataSet['measures'] [] = $measure;
                }
                
                // slices
                $dataSet['slices'] = array ();
                $sliceKeys = $query->getSliceKeys(
                    $dataSet ['dataStructureDefinition']['__cv_uri'], $dataSet['__cv_uri']
                );
                foreach ($sliceKeys as $sliceKey) {
                    $dataSet['slices'] = array_merge ($dataSet['slices'], $sliceKey ['slices']);
                }
                
                // dimensions
                $dataSet['dimensions'] = $query->getComponents(
                    $dataSet ['dataStructureDefinition']['__cv_uri'], $dataSet['__cv_uri'],
                    DataCube_UriOf::Dimension
                );
                
                $this->view->dataSets [] = $dataSet;
            }
        }
    }
    
    /**
     * 
     * @param void
     * @return void
     */
    public function compareAction()
    {       
        // set paths
        $basePath = $this->view->basePath = $this->_config->staticUrlBase . 'extensions/cubeviz/';
        $baseCssPath = $basePath .'public/css/';
        $baseJavascriptPath = $basePath .'public/javascript/';
        $baseImagesPath = $basePath .'public/images/';
        
        /**
         * Including css files for this action
         */
        $this->view->headLink()
            ->appendStylesheet($baseCssPath.'foreign/Bootstrap/bootstrap.min.css')
            ->appendStylesheet($baseCssPath.'foreign/FontAwesome/css/font-awesome.min.css')
            ->appendStylesheet($baseCssPath.'/CompareAction/CompareAction.css')
            ->appendStylesheet($baseCssPath.'/main.css');
            
        // Libraries
        $this->view->headScript()
            ->appendFile($basePath          .'ChartConfig.js',                     'text/javascript')            
            ->appendFile($baseJavascriptPath.'libraries/highcharts.js',            'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/highcharts-more.js',       'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/CryptoJS_Md5.js',          'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/json2.js',                 'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/javascriptStats-1.0.1.js', 'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/formulaParser.js',         'text/javascript')
            
            ->appendFile($baseJavascriptPath.'libraries/underscore.js',            'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/underscore.string.js',     'text/javascript')
            ->appendScript ('_.mixin(_.str.exports());'); // for underscore.string
            
        // If this module is in the "development" context
        if('development' === $this->_privateConfig->get('context')) {
            $this->view->headScript()
                ->appendFile ($baseJavascriptPath. 'libraries/munit.js', 'text/javascript')
                ->appendFile ($baseJavascriptPath. 'Test.js', 'text/javascript')
                ->appendFile ($baseJavascriptPath. 'Main.js', 'text/javascript');
        
        // otherwise it is in "production" context
        } else {
            $this->view->headScript()
                ->appendFile ($baseJavascriptPath. 'Main-production.js', 'text/javascript');
        }
        
        $this->view->translate = $this->_owApp->translate;
        $this->view->staticUrlBase = $this->_config->staticUrlBase;
        $this->view->cubevizImagesPath = $basePath .'public/images/';
        
        $on = $this->_owApp->getNavigation();
        $on->disableNavigation (); // disable OntoWiki's Navigation 
        
        /**
         * load data and setup view
         */
        $store = $this->_erfurt->getStore();
        $th = new OntoWiki_Model_TitleHelper (null, $store);
        
        $models = $store->getAvailableModels();
        $this->view->models = array ();    
                
        // config for frontend
        if(false === CubeViz_ViewHelper::$isCubeVizAppLoaded) {  
            
            $config['backend'] = array(
                'context'               => $this->_privateConfig->get('context'), 
                'database'              => $this->_owApp->getConfig()->store->backend,
                'dataHash'              => '',
                'imagesPath'            => $baseImagesPath,
                'modelInformation'      => array(),
                'modelUrl'              => '',
                'serviceUrl'            => '',
                'uiHash'                => '',
                'uiParts'               => array(
                    'dataselectionModule' => array ()
                ),
                'uiSettings'            => array (),
                'retrievedObservations' => array(),
                'url'                   => $this->_config->staticUrlBase . 'cubeviz/'
            );
            
            $this->view->headScript()
                 ->appendScript('cubeVizApp._ = '. json_encode($config, JSON_FORCE_OBJECT) .';')
                 ->appendScript('cubeVizApp._.backend.chartConfig = CubeViz_ChartConfig;');
        }
            
        // each element will be an object (acting as an associative array)
        // with "1" or "2" as key (index)
        $config['compareAction'] = array(
            'datasets'                      => array(1 => null, 2 => null),
            'components'                    => array(
                'dimensions'    => array (1 => null, 2 => null),
                'measures'      => array (1 => null, 2 => null),
                'attributes'    => array (1 => null, 2 => null)
            ),
            'models'                        => array(1 => null, 2 => null),
            'originalObservations'          => array(1 => null, 2 => null),
            'retrievedObservations'         => array(1 => null, 2 => null),
            'slices'                        => array(1 => null, 2 => null),
            
            'equalDimensions'               => array(),
            'unequalDimensions'             => array(1 => null, 2 => null),
            
            'numberOfObservations'          => array(1 => -1, 2 => -1)
        );
        
        $this->view->headScript()
             ->appendScript('cubeVizApp._.compareAction = '. json_encode($config['compareAction'], JSON_FORCE_OBJECT) .';');
        
        // modellist
        foreach ($models as $modelUri => $entry) { $th->addResource ($modelUri); }
        foreach ($models as $modelUri => $entry) { 
            $this->view->models [] = array (
                'label' => $th->getTitle ($modelUri),
                'uri' => $modelUri
            );
        }
    }    
    
    /**
     *
     */
    public function createexamplecubeAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        if('development' === $this->_privateConfig->get('context')) {
            try {
                $exampleCubeNs = "http://example.cubeviz.org/datacube/";
                
                $m = Erfurt_App::getInstance()->getStore()->getNewModel(
                    $exampleCubeNs, '', Erfurt_Store::MODEL_TYPE_OWL, false
                );
                
                // set file related stuff
                $ttl = file_get_contents ( __DIR__ .'/assets/exampleCube.ttl' );
                
                // import given file content
                Erfurt_App::getInstance ()->getStore()->importRdf (
                    $exampleCubeNs, $ttl, 'ttl', Erfurt_Syntax_RdfParser::LOCATOR_DATASTRING
                );
                
                $code = 200;
                $content = array(
                    'code' => $code,
                    'message' => 'Model was successfully created'
                );
            } catch (Exception $e) {
                $code = 400;
                $content = array(
                    'code' => $code,
                    'message' => $e->getMessage()
                );
            }
            
            $this->_sendJSONResponse($content, $code);
        }
    }
    
    /**
     * Exports dataselection given by dataHash.
     */
    public function exportAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        // do nothing, if export was deactivated
        if (false === $this->_privateConfig->get('useExport')) {
            return;
        }
        
        $model = $this->_owApp->selectedModel;
        
        /**
         * all these parameter pointing to the same dataHash
         */
        $dataselection = $this->_request->getParam ('dataselection', '');
        $datacube = $this->_request->getParam ('datacube', '');
        
        if ('' != $dataselection) {
            $dataHash = $dataselection;
        } elseif ('' != $datacube) {
            $dataHash = $datacube;
        } else {
            return;
        }
        
        // optional parameter
        $type = $this->_request->getParam ('type', '');
        
        $filename = 'cubevizExport_'. $dataHash;
        
        switch ($type)
        {
            case 'csv': // comma separated file
                $contentType = 'text/csv';
                $filename .= '.csv';
                break;
            
            default: // turtle
                $contentType = 'application/x-turtle';
                $filename .= '.ttl';
                break;
        }
        
        // setup response
        try {
            $output = CubeViz_Exporter::_(
                $type, 
                $dataHash, 
                $model, 
                $this->_titleHelperLimit,
                $this->_dimensionElementLimit
            );
            
            $this->getResponse()
                ->setHeader('Content-Type', $contentType, true)
                ->setHeader('Content-Disposition', 'filename="' . $filename . '"')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', '0');
            
            echo $output;
            
        } catch (Exception $e) {
            if('development' === $this->_privateConfig->get('context')) {
                throw $e;
            } else {
                echo "Something went wrong with the Exporter, ".
                     "please contact the side administrator if the problem persists. ".
                     "Sorry.";
            }
        }
    }
    
    /**
     * 
     */
    public function getcomponentsAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        // parameter
        $modelIri = $this->_request->getParam ('modelIri', '');
        $dsdUrl = $this->_request->getParam('dsdUrl', '');
        $dsUrl = $this->_request->getParam('dsUrl', '');
        $componentType = $this->_request->getParam('componentType', ''); 
        
        // check if model there
        if(false === $this->_erfurt->getStore()->isModelAvailable($modelIri)) {
            $code = 404;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'Model not available'
                ),
                $code
            );
            return;
        }
        
        // check if dsdUrl is valid
        if(false === Erfurt_Uri::check($dsdUrl)) {
            $code = 400;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'dsdUrl is not valid'
                ),
                $code
            );
            return;
        }
        
        // check if dsUrl is valid
        if(false === Erfurt_Uri::check($dsUrl)) {
            $code = 400;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'dsUrl is not valid'
                ),
                $code
            );
            return;
        }
                
        if($componentType == 'attribute') {
            $componentType = DataCube_UriOf::Attribute;
        } elseif($componentType == 'dimension') {
            $componentType = DataCube_UriOf::Dimension;
        } elseif($componentType == 'measure') {
            $componentType = DataCube_UriOf::Measure;
        } else {
            // stop execution, because it is not a $componentType that i understand
            $code = 400;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'componentType was wheter component nor measure'
                ),
                $code
            );
            return;
        }
        
        try {
            $model = new Erfurt_Rdf_Model($modelIri);
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );
            
            $code = 200;
            $content = array(
                'code' => $code,
                'content' => $query->getComponents($dsdUrl, $dsUrl, $componentType),
                'message' => ''
            );
        } catch(CubeViz_Exception $e) {
            $code = 400;
            $content = array(
                'code' => $code, 
                'content' => '', 
                'message' => 'compontent type was wheter component nor measure'
            );
        }
        
        $this->_sendJSONResponse($content, $code);
    }
    
    /**
     * 
     */
    public function getdatasetsAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        // parameter
        $m = $this->_request->getParam ('modelIri', '');        
        $dsdUrl = $this->_request->getParam('dsdUrl', '');

        // check if model there
        if(false === $this->_erfurt->getStore()->isModelAvailable($m)) {
            $code = 404;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'Model not available'
                ),
                $code
            );
            return;
        }
    
        // check if dsdUrl is valid
        if('' != $dsdUrl && false === Erfurt_Uri::check($dsdUrl)) {
            $code = 400;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'dsdUrl is not valid'
                ),
                $code
            );
            return;
        }

        // load data sets
        try {
            $model = new Erfurt_Rdf_Model ($m);
            
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );
            
            $code = 200;
            $content = array(
                'code' => $code, 
                'content' => $query->getDataSets($dsdUrl),
                'message' => ''
            );
            
        } catch(Exception $e) {
            $code = 400;
            $content = array(
                'code' => $code, 
                'content' => '', 
                'message' => $e->getMessage()
            );
        }
        
        $this->_sendJSONResponse($content, $code);
    }
    
    /**
     * 
     */
    public function getdatastructuredefinitionsAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        // check model parameter
        $modelIri = $this->_request->getParam ('modelIri', '');

        // check if model there
        if(false === $this->_erfurt->getStore()->isModelAvailable($modelIri)) {
            $code = 404;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '',
                    'message' => 'Model not available'
                ),
                $code
            );
            return;
        }
        
        try {
            $model = new Erfurt_Rdf_Model($modelIri);            
            
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );            

            $code = 200;
            $content = array(
                'code' => $code,
                'content' => $query->getDataStructureDefinitions(),
                'message' => ''
            );
        } catch(Exception $e) {
            $code = 404;
            $content = $e->getMessage();
        }
        
        $this->_sendJSONResponse($content, $code);
    }
    
    /**
     *
     */
    public function getnumberofobservationsAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();   
             
        // parameter
        $modelIri = $this->_request->getParam ('modelIri', '');
        $dsUri = trim($this->_request->getParam ('dsUri', ''));
        
        // check if model there
        if(false === $this->_erfurt->getStore()->isModelAvailable($modelIri)) {
            $code = 404;
            $this->_sendJSONResponse(
                array('code' => $code, 'content' => '', 'message' => 'Model not available'),
                $code
            );
            return;
        }
        
        // check if model there
        if(false === Erfurt_Uri::check($dsUri)) {
            $code = 404;
            $this->_sendJSONResponse(
                array('code' => $code, 'content' => '', 'message' => 'Dataset URI is not valid: '. $dsUri),
                $code
            );
            return;
        }
            
        try {
            $model = new Erfurt_Rdf_Model ($modelIri);
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );

            $code = 200;

            $content = array(
                'code' => $code, 
                'content' => $query->getNumberOfObservations($dsUri),
                'message' => ''
            );
            
        } catch (Exception $e) {
            $code = 400;
            $content = array('code' => $code, 'content' => '', 'message' => $e->getMessage());
        }
        
        $this->_sendJSONResponse($content, $code);
    }
    
    /**
     *
     */
    public function getobservationsAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();   
             
        // parameter
        $modelIri = $this->_request->getParam ('modelIri', '');
        $dataHash = trim($this->_request->getParam ('cv_dataHash', ''));
        $datasetUri = trim($this->_request->getParam ('datasetUri', ''));
        
        // check if model there
        if(false === $this->_erfurt->getStore()->isModelAvailable($modelIri)) {
            $code = 404;
            $this->_sendJSONResponse(
                array('code' => $code, 'content' => '', 'message' => 'Model not available'),
                $code
            );
            return;
        }
        
        // check if datahash or dataset uri set
        if('' == $dataHash && false === Erfurt_Uri::check($datasetUri)) {
            if('' == $dataHash) {
                $code = 404;
                $this->_sendJSONResponse(
                    array('code' => $code, 'content' => '', 'message' => 'Data hash is not valid'),
                    $code
                );
                return;
            }
            // false === Erfurt_Uri::check($datasetUri)
            else {
                $code = 404;
                $this->_sendJSONResponse(
                    array('code' => $code, 'content' => '', 'message' => 'Dataset Uri is not valid'),
                    $code
                );
                return;
            }
        }
            
        try {
            $model = new Erfurt_Rdf_Model ($modelIri);
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );
            
            if('' != $dataHash) {
                $configuration = new CubeViz_ConfigurationLink(
                    $this->_owApp->selectedModel,
                    $this->_titleHelperLimit,
                    $this->_dimensionElementLimit
                );

                // load configuration which is associated with given linkCode
                list($c, $hash) = $configuration->read ($dataHash, 'data');
                
                $datasetUri = $c ['selectedDS']['__cv_uri'];
                $dimensions = $c ['selectedComponents']['dimensions'];
            
            // datasetUri set
            } else {
                $dimensions = array();
            }
            
            $code = 200;

            $content = array(
                'code' => $code, 
                'content' => $query->getObservations(
                    $datasetUri,
                    $dimensions
                ),
                'message' => ''
            );
            
        } catch (Exception $e) {
            $code = 400;
            $content = array('code' => $code, 'content' => '', 'message' => $e->getMessage());
        }
        
        $this->_sendJSONResponse($content, $code);
    }
    
    /**
     *
     */
    public function getslicesAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();   
             
        // parameter
        $modelIri = $this->_request->getParam ('modelIri', '');
        $dsdUrl = $this->_request->getParam ('dsdUrl', '');
        $dsUrl = $this->_request->getParam ('dsUrl', '');
        
        // check if model there
        if(false === $this->_erfurt->getStore()->isModelAvailable($modelIri)) {
            $code = 404;
            $this->_sendJSONResponse(
                array('code' => $code, 'content' => '', 'message' => 'Model not available'),
                $code
            );
            return;
        }        
        
        // check if dsdUrl is valid
        if(false === Erfurt_Uri::check($dsdUrl)) {
            $code = 400;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'dsdUrl is not valid'
                ),
                $code
            );
            return;
        }
        
        // check if dsUrl is valid
        if(false === Erfurt_Uri::check($dsUrl)) {
            $code = 400;
            $this->_sendJSONResponse(
                array(
                    'code' => $code, 
                    'content' => '', 
                    'message' => 'dsUrl is not valid'
                ),
                $code
            );
            return;
        }
            
        try {
            $model = new Erfurt_Rdf_Model ($modelIri);
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );
            $code = 200;
            
            // result object
            $content = array(
                'code' => $code, 
                'content' => array (),
                'message' => ''
            );

            // get slice keys
            $sliceKeys = $query->getSliceKeys($dsdUrl, $dsUrl);
            
            // collect all slices in one list
            foreach ($sliceKeys as $sliceKey) {
                $content ['content'] = array_merge ($content ['content'], $sliceKey ['slices']);
            }
            
        } catch (Exception $e) {
            $code = 400;
            $content = array('code' => $code, 'content' => '', 'message' => $e->getMessage());
        }
        
        $this->_sendJSONResponse($content, $code);
    }
    
    /**
     * 
     */
    public function indexAction () 
    {
        // In case no model was selected, it redirect to the root url of OntoWiki
        if ( false === isset($this->_owApp->selectedModel)) {
            $this->_helper->redirector->gotoUrl('/');
            return;
        }
    
        /**
         * Set paths
         */
        $basePath = $this->view->basePath = $this->_config->staticUrlBase . 'extensions/cubeviz/';
        $baseJavascriptPath = $basePath .'public/javascript/';
        $baseCssPath = $basePath .'public/css/';
        $this->view->cubevizImagesPath = $baseImagesPath = $basePath .'public/images/';
        $this->view->translate = $this->_owApp->translate;
    
        /**
         * Including javascript files for this action
         */
        // Libraries
        $this->view->headScript()
            ->appendFile($baseJavascriptPath.'libraries/javascriptStats-1.0.1.js', 'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/highcharts.js', 'text/javascript')
            ->appendFile($baseJavascriptPath.'libraries/highcharts-more.js', 'text/javascript');  
    
        /**
         * Including css files for this action
         */
        $this->view->headLink()
            ->prependStylesheet($baseCssPath.'foreign/Bootstrap/bootstrap.min.css')
            ->prependStylesheet($baseCssPath.'/main.css')
            ->prependStylesheet($baseCssPath.'/IndexAction/exportArea.css')
            ->prependStylesheet($baseCssPath.'/IndexAction/header.css')
            ->prependStylesheet($baseCssPath.'/IndexAction/legend.css')
            ->prependStylesheet($baseCssPath.'/IndexAction/visualization.css')
            ->prependStylesheet($baseCssPath.'/IndexAction/visualizationSelector.css');
        
    
        /**
         * Load model information
         */
        $model = $this->_owApp->selectedModel;
        $modelIri = $model->getModelIri();
        $modelStore = $model->getStore();
        $modelInformation = CubeViz_ViewHelper::getModelInformation($modelStore, $model, $modelIri);
        $modelInformation ['rdfs:label'] = true === isset($modelInformation ['http://www.w3.org/2000/01/rdf-schema#label'])
            ? $modelInformation ['http://www.w3.org/2000/01/rdf-schema#label']['content']
            : $modelIri;        
        
        $serviceUrl = true === isset($_SESSION ['ONTOWIKI']['serviceUrl'])
            ? $_SESSION ['ONTOWIKI']['serviceUrl']
            : null;
            
        /**
         * Set view and some of its properties.
         */        
        // fill title-field
        $this->view->placeholder('main.window.title')
                   ->set('Visualization for '. $modelInformation ['rdfs:label']);
        
        $on = $this->_owApp->getNavigation();
        $on->disableNavigation (); // disable OntoWiki's Navigation    
        
        CubeViz_ViewHelper::$isCubeVizIndexLoaded = true;

        // init cubeVizApp
        try {
            $config = CubeViz_ViewHelper::initApp(
                $this->view,
                $model,
                $this->_owApp->getConfig()->store->backend,
                $this->_privateConfig->get('context'),
                $modelIri,
                $serviceUrl,
                $this->_config->staticUrlBase,
                $baseImagesPath,
                $this->_request->getParam ('cv_dataHash'),
                $this->_request->getParam ('cv_uiHash'),
                $modelInformation,
                $this->_titleHelperLimit,
                $this->_dimensionElementLimit
            );
        } catch (Exception $e) {
            $this->_redirect(
                OntoWiki::getInstance()->getConfig()->urlBase . "/cubeviz/modelinfo",
                array('code' => 302)
            );
        }
        
        // load settings from the doap.n3
        $this->view->useExport = $this->_privateConfig->get('useExport');
        $this->view->uiUseDataSetInsteadOfModel = true !== $this->_privateConfig->get('uiUseDataSetInsteadOfModel')
            ? 'false' : 'true';
        
        if(null !== $config) {
            $this->view->headScript()
                 ->appendScript('cubeVizApp._ = '. json_encode($config, JSON_FORCE_OBJECT) .';')
                 ->appendScript('cubeVizApp._.backend.chartConfig = CubeViz_ChartConfig;');
        }
    }
    
    /**
     * 
     */
    public function modelinfoAction()
    {
        if (true === isset($this->_request->m)) {
            // reset resource/class
            unset($this->_owApp->selectedResource);
            unset($this->_owApp->selectedClass);
            unset($this->_session->hierarchyOpen);
        }
        
        $on = $this->_owApp->getNavigation();
        $on->disableNavigation (); // disable OntoWiki's Navigation    
        
        /**
         * Load model information
         */
        $graph       = $this->_owApp->selectedModel;
        $resource    = $this->_owApp->selectedResource;
        $erfurt      = $this->_owApp->erfurt;  
        $store       = $this->_owApp->erfurt->getStore();
        
        /**
         * Load model information
         */
        $model = $this->_owApp->selectedModel;
        $modelIri = $model->getModelIri();
        $modelStore = $model->getStore();
        $modelInformation = CubeViz_ViewHelper::getModelInformation($modelStore, $model, $modelIri);
        $modelInformation ['rdfs:label'] = true === isset($modelInformation ['http://www.w3.org/2000/01/rdf-schema#label'])
            ? $modelInformation ['http://www.w3.org/2000/01/rdf-schema#label']['content']
            : $modelIri;
        
        $this->view->sparqlService = $erfurt->getConfig()->store->sparql->serviceUrl;
        
        $this->view->modelTitle = $modelInformation ['rdfs:label'];
        
        $this->view->modelDescriptions = array();
        foreach ($modelInformation as $url => $entry) 
        {
            if ('http://www.w3.org/2004/02/skos/core#description' == $url
                || 'http://www.w3.org/2000/01/rdf-schema#comment' == $url) {
                $this->view->modelDescriptions [] = $entry ['content'];
            }
        }
        
        // check for DataCube information
        $query = new DataCube_Query(
            $model, 
            $this->_titleHelperLimit, 
            $this->_dimensionElementLimit
        );
        $this->view->containsDataCubeInformation = $query->containsDataCubeInformation();
    }
    
    /**
     *
     */
    public function removeexamplecubeAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        if('development' === $this->_privateConfig->get('context')) {
            
            $exampleCubeNs = "http://example.cubeviz.org/datacube/";
            
            // if model exists, remove it
            if(true == Erfurt_App::getInstance ()->getStore()->isModelAvailable($exampleCubeNs)){
                try {
                    Erfurt_App::getInstance ()->getStore()->deleteModel (
                        $exampleCubeNs, false
                    );
                    
                    $code = 200;
                    $content = array(
                        'code' => $code,
                        'content' => '',
                        'message' => 'Model removed successfully'
                    );
                } catch (Exception $e) {
                    $code = 400;
                    $content = array(
                        'code' => $code,
                        'content' => '',
                        'message' => $e->getMessage()
                    );
                }
                
            // model does not exists
            } else {
                $code = 400;
                $content = array(
                    'code' => $code,
                    'content' => '',
                    'message' => 'Model does not exists'
                );
            }
            
            $this->_sendJSONResponse($content, $code);
        }
    }
    
    /**
     * 
     */
    public function savecontenttofileAction() 
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        /**
         * save parameter 
         */
        $hash = $this->_request->getParam('hash', '');
        $modelIri = $this->_request->getParam('modelIri', '');
        $stringifiedContent = $this->_request->getParam('stringifiedContent', '');
        $type = $this->_request->getParam('type', '');
        $useObservations = $this->_request->getParam('useObservations', '');
        
        // if type is data, than load observations before save content
        if ('data' == $type && 'false' == $useObservations) {
            
            // setup 
            $model = new Erfurt_Rdf_Model($modelIri);
            $query = new DataCube_Query(
                $model, 
                $this->_titleHelperLimit, 
                $this->_dimensionElementLimit
            );
            
            // decode content
            $content = json_decode($stringifiedContent, true);
            
            // load observations
            $content ['retrievedObservations'] = $query->getObservations(
                $content ['selectedDS']['__cv_uri'],
                $content ['selectedComponents']['dimensions']
            );
            
            $stringifiedContent = json_encode($content, JSON_FORCE_OBJECT);
        }
        
        // write given content to file
        $configuration = new CubeViz_ConfigurationLink(
            $this->_owApp->selectedModel, 
            $this->_titleHelperLimit,
            $this->_dimensionElementLimit
        );
                
        $configuration->write($stringifiedContent, $hash);
        
        // send back generated hash
        $this->_sendJSONResponse(null);
    }
    
    /**
     * 
     */
    protected function _sendJSONResponse($content, $code = 200)
    {
        $this->_response
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT')
            ->setHttpResponseCode($code)
            ->setBody(json_encode($content));
    }
}
