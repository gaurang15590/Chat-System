<?php

namespace App\Command;

use App\WebSocket\WebSocketServer;
use React\EventLoop\Loop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'websocket:server',
    description: 'Start the WebSocket server for real-time chat'
)]
class WebSocketServerCommand extends Command
{
    public function __construct(
        private WebSocketServer $webSocketServer
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

        $io->info("Starting WebSocket server on {$host}:{$port}");
        
        try {
            $this->webSocketServer->start();
            
            $io->success('WebSocket server started successfully!');
            $io->note('Press Ctrl+C to stop the server');
            
            // Keep the server running
            Loop::get()->run();
            
        } catch (\Exception $e) {
            $io->error('Failed to start WebSocket server: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}