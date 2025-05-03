<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:recherche-entreprise',
    description: 'Recherche une entreprise via l\'API Recherche d\'Entreprises',
)]
class RechercheEntrepriseCommand extends Command
{
    private const API_URL = 'https://recherche-entreprises.api.gouv.fr/search';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('query', InputArgument::REQUIRED, 'Terme de recherche')
            ->addOption('page', 'p', InputOption::VALUE_OPTIONAL, 'Numéro de page', '1')
            ->addOption('per-page', 'nb', InputOption::VALUE_OPTIONAL, 'Nombre de résultats par page (max 25)', '10')
            ->addOption('activite-principale', 'a', InputOption::VALUE_OPTIONAL, 'Code NAF/APE (ex: 01.12Z,28.15Z)')
            ->addOption('categorie-entreprise', 'c', InputOption::VALUE_OPTIONAL, 'Catégorie d\'entreprise (PME, ETI, GE)')
            ->addOption('code-postal', 'z', InputOption::VALUE_OPTIONAL, 'Code postal')
            ->addOption('departement', 'd', InputOption::VALUE_OPTIONAL, 'Code département')
            ->addOption('region', 'r', InputOption::VALUE_OPTIONAL, 'Code région')
            ->addOption('etat-administratif', 'ea', InputOption::VALUE_OPTIONAL, 'État administratif (A: Actif, C: Cessé)')
            ->addOption('nature-juridique', 'j', InputOption::VALUE_OPTIONAL, 'Nature juridique')
            ->addOption('section-activite', 's', InputOption::VALUE_OPTIONAL, 'Section d\'activité (A à U)')
            ->addOption('tranche-effectif', 't', InputOption::VALUE_OPTIONAL, 'Tranche d\'effectif')
            ->addOption('minimal', 'm', InputOption::VALUE_NONE, 'Retourne une réponse minimale')
            ->addOption('include', 'i', InputOption::VALUE_OPTIONAL, 'Champs à inclure (complements, dirigeants, finances, matching_etablissements, siege, score)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $query = $input->getArgument('query');
        
        $queryParams = [
            'q' => $query,
            'page' => $input->getOption('page'),
            'per_page' => $input->getOption('per-page'),
        ];

        // Ajout des paramètres optionnels
        $optionalParams = [
            'activite_principale',
            'categorie_entreprise',
            'code_postal',
            'departement',
            'region',
            'etat_administratif',
            'nature_juridique',
            'section_activite',
            'tranche_effectif',
        ];

        foreach ($optionalParams as $param) {
            $value = $input->getOption(str_replace('_', '-', $param));
            if ($value) {
                $queryParams[$param] = $value;
            }
        }

        // Gestion des options minimal et include
        if ($input->getOption('minimal')) {
            $queryParams['minimal'] = 'true';
            if ($include = $input->getOption('include')) {
                $queryParams['include'] = $include;
            }
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => $queryParams,
            ]);

            $data = json_decode($response->getContent(), true);

            if (empty($data['results'])) {
                $io->warning('Aucun résultat trouvé pour votre recherche.');
                return Command::SUCCESS;
            }

            $io->title('Résultats de la recherche :');
            
            foreach ($data['results'] as $entreprise) {
                $io->section($entreprise['nom_raison_sociale']);
                $io->writeln(sprintf('SIREN : %s', $entreprise['siren']));
                if (isset($entreprise['siret'])) {
                    $io->writeln(sprintf('SIRET : %s', $entreprise['siret']));
                }
                
                if (isset($entreprise['siege'])) {
                    $io->writeln(sprintf('Adresse : %s', $entreprise['siege']['adresse']));
                    $io->writeln(sprintf('Code postal : %s', $entreprise['siege']['code_postal']));
                    $io->writeln(sprintf('Ville : %s', $entreprise['siege']['libelle_commune']));
                }

                if (isset($entreprise['activite_principale'])) {
                    $io->writeln(sprintf('Activité principale : %s', $entreprise['activite_principale']));
                }

                if (isset($entreprise['categorie_entreprise'])) {
                    $io->writeln(sprintf('Catégorie : %s', $entreprise['categorie_entreprise']));
                }

                if (isset($entreprise['tranche_effectif_salarie'])) {
                    $io->writeln(sprintf('Effectif : %s', $entreprise['tranche_effectif_salarie']));
                }

                if (isset($entreprise['complements'])) {
                    $complements = $entreprise['complements'];
                    if ($complements['est_association']) {
                        $io->writeln('Type : Association');
                    }
                    if ($complements['est_ess']) {
                        $io->writeln('Type : Économie Sociale et Solidaire');
                    }
                    if ($complements['est_service_public']) {
                        $io->writeln('Type : Service Public');
                    }
                }

                $io->newLine();
            }

            $io->info(sprintf('Page %s/%s - %s résultats trouvés', 
                $data['page'], 
                $data['total_pages'], 
                $data['total_results']
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Une erreur est survenue : %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
} 