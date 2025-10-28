<?php

namespace App\Command;

use App\WebSocket\FleetWebSocketServer;
use React\EventLoop\Loop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'websocket:fleet',
    description: 'Start a WebSocket server in fleet mode with pub/sub messaging'
)]
class WebSocketFleetCommand extends Command
{
    public function __construct(
        private FleetWebSocketServer $fleetWebSocketServer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'WebSocket server host', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'WebSocket server port', 8080)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $io->info("Starting WebSocket Fleet Server on {$host}:{$port}");
        $io->note('This server supports multi-server real-time chat with pub/sub messaging');
        
        try {
            $this->fleetWebSocketServer->start();
            
            $io->success('WebSocket Fleet Server started successfully!');
            $io->note('Press Ctrl+C to stop the server');
            
            // Keep the server running
            Loop::get()->run();
            
        } catch (\Exception $e) {
            $io->error('Failed to start WebSocket Fleet Server: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}