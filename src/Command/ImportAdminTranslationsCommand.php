<?php

namespace App\Command\DataMigration;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\Translation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportAdminTranslationsCommand extends AbstractCommand
{
    protected static $defaultName = 'hoppecke:import:admin-translations';
    
    protected function configure()
    {
        $this
            ->setDescription('Import admin translations from Excel file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting admin translations import');

        $filePath = PIMCORE_PROJECT_ROOT . '/data-migration/translation_admin.xlsx';
        
        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");
            return 1;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            array_shift($rows);

            $translationsData = [];
            foreach ($rows as $row) {
                if (count($row) < 5) {
                    continue;
                }

                $key = $row[0];
                $language = $row[1];
                $text = $row[2];
                $creationDate = !empty($row[3]) ? $row[3] : time();
                $modificationDate = !empty($row[4]) ? $row[4] : time();

                if (empty($key) || empty($language)) {
                    continue;
                }

                if (!isset($translationsData[$key])) {
                    $translationsData[$key] = [
                        'translations' => [],
                        'creationDate' => $creationDate,
                        'modificationDate' => $modificationDate
                    ];
                }

                $translationsData[$key]['translations'][$language] = $text;
                
                if ($creationDate < $translationsData[$key]['creationDate']) {
                    $translationsData[$key]['creationDate'] = $creationDate;
                }
                if ($modificationDate > $translationsData[$key]['modificationDate']) {
                    $translationsData[$key]['modificationDate'] = $modificationDate;
                }
            }
            
            $io->section('Importing admin translations...');
            $progressBar = $io->createProgressBar(count($translationsData));
            $successCount = 0;
            $errorCount = 0;


            foreach ($translationsData as $key => $data) {
                try {
                    $translationListing = new Translation\Listing();
                    $translationListing->setCondition("`key` = ? AND `type` = 'admin'", [$key]);
                    $existingTranslations = $translationListing->load();
                    
                    $translation = null;
                    if (!empty($existingTranslations)) {
                        $translation = $existingTranslations[0];
                    } else {
                        $translation = new Translation();
                        $translation->setKey($key);
                        $translation->setType('admin');
                        $translation->setCreationDate($data['creationDate']);
                    }
                    
                    foreach ($data['translations'] as $language => $text) {
                        $translation->addTranslation($language, $text);
                    }
                    
                    $translation->setModificationDate($data['modificationDate']);
                    $translation->save();
                    
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $io->error(sprintf(
                        'Error importing admin translation for key "%s": %s',
                        $key,
                        $e->getMessage()
                    ));
                    
                    if ($output->isVerbose()) {
                        $io->writeln($e->getTraceAsString());
                    }
                }
                
                $progressBar->advance();
                
                if ($successCount % 100 === 0) {
                    \Pimcore::collectGarbage();
                }
            }
            
            $progressBar->finish();
            $io->newLine(2);
            
            $io->success(sprintf(
                'Admin translations import completed: %d successful, %d failed (total translation keys)',
                $successCount,
                $errorCount
            ));
            
            return 0;
        } catch (\Exception $e) {
            $io->error('Error during import: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return 1;
        }
    }
}