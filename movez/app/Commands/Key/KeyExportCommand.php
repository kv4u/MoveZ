<?php
declare(strict_types=1);

namespace App\Commands\Key;

use App\Services\Encryptor;
use LaravelZero\Framework\Commands\Command;

class KeyExportCommand extends Command
{
    protected $signature = 'key:export
                            {--output= : Write the key to this file (created with 0600 permissions) instead of printing it}';

    protected $description = 'Print the encryption key so it can be imported on another machine';

    public function handle(Encryptor $encryptor): int
    {
        $created = !$encryptor->hasKey();
        $key     = 'movez-key:' . $encryptor->exportKey();
        $output  = $this->option('output');

        // Everything except the key goes to stderr, so stdout can be piped:
        //   movez key:export | ssh other-machine movez key:import
        $stderr = $this->getOutput()->getErrorStyle();

        if ($created) {
            $stderr->writeln("<comment>No key existed yet — created a new one at {$encryptor->keyPath()}</comment>");
        }

        if (is_string($output) && $output !== '') {
            touch($output);
            chmod($output, 0600);
            file_put_contents($output, $key . PHP_EOL);
            $stderr->writeln("<info>Key written to {$output}</info>");
        } else {
            $this->line($key);
        }

        $stderr->writeln(
            "<comment>Fingerprint {$encryptor->fingerprint()}. Treat this key like a password: anyone with it can decrypt your sessions.</comment>"
        );

        return self::SUCCESS;
    }
}
