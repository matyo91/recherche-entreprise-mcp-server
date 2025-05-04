<?php

namespace App\Command;

use App\Service\DatasetExperimentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:run-dataset-experiment',
    description: 'Run a dataset experiment using Arize'
)]
class RunDatasetExperimentCommand extends Command
{
    public function __construct(
        private readonly DatasetExperimentService $datasetExperimentService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('experiment-name', InputArgument::REQUIRED, 'Name of the experiment')
            ->addArgument('features-file', InputArgument::REQUIRED, 'Path to the features JSON file')
            ->addArgument('predictions-file', InputArgument::REQUIRED, 'Path to the predictions JSON file')
            ->addArgument('ground-truth-file', InputArgument::OPTIONAL, 'Path to the ground truth JSON file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $experimentName = $input->getArgument('experiment-name');

        try {
            // Load and validate input files
            $features = $this->loadJsonFile($input->getArgument('features-file'));
            $predictions = $this->loadJsonFile($input->getArgument('predictions-file'));
            $groundTruth = $input->getArgument('ground-truth-file') 
                ? $this->loadJsonFile($input->getArgument('ground-truth-file'))
                : [];

            // Validate data structure
            if (count($features) !== count($predictions)) {
                throw new \RuntimeException('Features and predictions arrays must have the same length');
            }

            if (!empty($groundTruth) && count($features) !== count($groundTruth)) {
                throw new \RuntimeException('Ground truth array must have the same length as features and predictions');
            }

            $io->info(sprintf('Starting experiment "%s" with %d samples', $experimentName, count($features)));

            // Create batch data
            $batchData = [];
            for ($i = 0; $i < count($features); $i++) {
                $batchData[] = [
                    'features' => $features[$i],
                    'predictions' => $predictions[$i],
                    'ground_truth' => $groundTruth[$i] ?? null,
                    'metadata' => [
                        'sample_index' => $i,
                        'timestamp' => (new \DateTime())->format('c')
                    ]
                ];
            }

            // Log batch predictions
            $this->datasetExperimentService->logBatchPredictions($experimentName, $batchData);

            $io->success('Experiment completed successfully!');
            $io->note('You can view the results in the Arize dashboard');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function loadJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException(sprintf('File not found: %s', $filePath));
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Invalid JSON in file %s: %s', $filePath, json_last_error_msg()));
        }

        return $data;
    }
} 