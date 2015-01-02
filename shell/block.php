<?php
/**
 * Import and export for blocks in magento
 * @package Devopensource_Shell_Block
 */
require 'abstract.php';

class Devopensource_Shell_Block extends Mage_Shell_Abstract {

    /**
     * Retrieve File_Csv instance
     *
     * @return Varien_File_Csv
     */
    protected function _getCsvFile()
    {
        return $csv = new Varien_File_Csv();
    }

    /**
     * Retrieve File_Csv instance
     *
     * @return Varien_File_Csv
     */
    protected function _getAllCsvData($file, $delimiter = ',', $enclosure = '"', $remote_file = false)
    {
        $csv  = $this->_getCsvFile();
        $csv->setDelimiter($delimiter);
        $csv->setEnclosure($enclosure);

        $io 	    = new Varien_Io_File();
        $path_stock = Mage::getBaseDir('var').DS.'import'.DS.'stock'.DS;

        if($remote_file){
            $currentDate = Mage::getModel('core/date')->date('YmdHis');
            $file_name   = 'stock_'.$currentDate.'.csv';
            $file_path   = $path_stock . $file_name;
            $io->read($file, $file_path); //save file remote
        }else{
            $file_path   =   $file;
        }

        $csv_data = ($io->fileExists($file_path)) ? $csv->getData($file_path) : false;

        return $csv_data;
    }

    /**
     * Export blocks in csv
     * @return array result of import
     */
    protected function _exportCmsBlock(){

        $io = new Varien_Io_File();

        $pathExportDir = Mage::getBaseDir('var') . DS . 'export' . DS;
        $name = 'blocks_'.date("w");
        $file = $pathExportDir . DS . $name . '.csv';

        $io->setAllowCreateFolders(true);
        $io->open(array('path' => $pathExportDir));

        $io->rm($file);

        $io->streamOpen($file, 'w+');

        $io->streamWriteCsv(array('block_id','title','identifier','content','creation_time','update_time','is_active'),';');

        $blocks = Mage::getModel('cms/block')->getCollection();
        $imported = 0;
        $blocksImported = array();
        foreach ($blocks as $_block) {
            $data = array();

            $data[] = $_block->getBlockId();
            $data[] = $_block->getTitle();
            $data[] = $_block->getIdentifier();
            $data[] = $_block->getContent();
            $data[] = $_block->getCreationTime();
            $data[] = $_block->getUpdateTime();
            $data[] = $_block->getIsActive();

            $blocksImported[] = $_block->getTitle() . "( " . $_block->getIdentifier() . "  )";

            $io->streamWriteCsv($data,';');
            $imported++;
        }

        $io->streamUnlock();
        $io->streamClose();

        return array('count_imported' => $imported, 'blocks' => $blocksImported);
    }

    /**
     * Import csv blocks
     * @return array result of import
     */
    protected function _importCmsBlock(){

        $pathExportDir = Mage::getBaseDir('var') . DS . 'export' . DS;
        $name = 'blocks_'.date("w");
        $file = $pathExportDir . DS . $name . '.csv';

        $dataCsv = $this->_getAllCsvData($file,';');

        $imported = 0;
        $blocksImported = array();

        for($i=1; $i<count($dataCsv); $i++) {

            $blockId        = $dataCsv[$i][0];
            $title          = $dataCsv[$i][1];
            $identifier     = $dataCsv[$i][2];
            $content        = $dataCsv[$i][3];
            $creationTime   = $dataCsv[$i][4];
            $updateTime     = $dataCsv[$i][5];
            $isActive       = $dataCsv[$i][6];

            $_tmpBlock = Mage::getModel('cms/block')->load($identifier,'identifier');

            //@todo: verificar datos de creaciones de tiempo
            if ( $_tmpBlock->getUpdateTime != $updateTime){

                $_tmpBlock->setData('content', $content);
                $_tmpBlock->setData('title', $title);
                $_tmpBlock->setData('is_active', $isActive);
                $_tmpBlock->save();


                $blocksImported[] = $title . "( " . $identifier . "  )";
                $imported++;
            }else{

                $_tmpBlock->setData('content', $content);
                $_tmpBlock->setData('title', $title);
                $_tmpBlock->setData('is_active', $isActive);
                $_tmpBlock->setData('identifier', $identifier);
                $_tmpBlock->setData('creation_time', $creationTime);

                $_tmpBlock->save();

                $blocksImported[] = $title . "( " . $identifier . "  )";
                $imported++;
            }
        }

        return array('count_imported' => $imported, 'blocks' => $blocksImported);
    }

    /**
     * Run script
     *
     */
    public function run()
    {
        $time = microtime(true);

        if ($this->getArg('export')) {

            $result =$this->_exportCmsBlock();

            echo "Messages: \n";

            foreach ($result['blocks'] as $_blockName) {
                echo $_blockName." -----> Exported \n";
            }

            echo $result['count_imported']." total blocks exported \n";

            echo 'Elapsed time total import: ' . round(microtime(true) - $time, 2) . 's' . "\n";

        } else if ($this->getArg('import')) {

            $result = $this->_importCmsBlock();

            echo "Messages: \n";

            foreach ($result['blocks'] as $_blockName) {
                echo $_blockName." -----> Imported \n";
            }

            echo $result['count_imported']." total blocks imported \n";

            echo 'Elapsed time total import: ' . round(microtime(true) - $time, 2) . 's' . "\n";

        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f blocks.php export
        php -f blocks.php import
USAGE;
    }

}

$blocks = new Devopensource_Shell_Block();
$blocks->run();