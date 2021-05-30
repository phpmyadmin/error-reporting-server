<?php

namespace App;

use App\Model\Table\IncidentsTable;
use Cake\Utility\Security;
use DateTime;
use stdClass;

use function array_filter;
use function array_keys;
use function array_merge;
use function bin2hex;
use function crc32;
use function date;
use function html_entity_decode;
use function htmlspecialchars_decode;
use function is_string;
use function json_decode;
use function openssl_random_pseudo_bytes;
use function parse_str;
use function parse_url;
use function strpos;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PHP_URL_QUERY;

/**
 * Represents an user report
 */
class Report extends stdClass
{
    /** @var string */
    private $internal____date = null;

    /** @var string */
    private $internal____eventId = null;

    /** @var string */
    private $internal____userMessage = null;

    public function hasUserFeedback(): bool
    {
        return $this->internal____userMessage !== null;
    }

    public function getUserFeedback(): string
    {
        return $this->internal____userMessage ?? '';
    }

    public static function fromString(string $input): Report
    {
        $obj = json_decode($input);

        return self::fromObject((object) $obj);
    }

    public static function fromObject(stdClass $input): Report
    {
        $obj = (object) $input;
        $keys = array_keys((array) $obj);
        $report = new Report();
        foreach ($keys as $propertyName) {
            if ($propertyName === 'steps') {
                $report->internal____userMessage = $obj->{$propertyName};
            } else {
                $report->{$propertyName} = $obj->{$propertyName};
            }
        }

        return $report;
    }

    public function setTimestamp(string $timestamp): void
    {
        $this->internal____date = $timestamp;
    }

    public function getEventId(): string
    {
        if ($this->internal____eventId === null) {
            $this->internal____eventId = bin2hex((string) openssl_random_pseudo_bytes(16));
        }

        return $this->internal____eventId;
    }

    public function getTimestampUTC(): string
    {
        return $this->internal____date ?? date(DateTime::RFC3339);
    }

    public function getTags(): stdClass
    {
        /*
            "pma_version": "4.8.6-dev",
            "browser_name": "CHROME",
            "browser_version": "72.0.3626.122",
            "user_os": "Linux",
            "server_software": "nginx/1.14.0",
            "user_agent_string": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.122 Safari/537.36 Vivaldi/2.3.1440.61",
            "locale": "fr",
            "configuration_storage": "enabled",
            "php_version": "7.2.16-1+ubuntu18.04.1+deb.sury.org+1",
            "exception_type": "php",
        */
        $tags = new stdClass();
        //$tags->pma_version = $this->{'pma_version'} ?? null;
        //$tags->browser_name = $this->{'browser_name'} ?? null;
        //$tags->browser_version = $this->{'browser_version'} ?? null;
        //$tags->user_os = $this->{'user_os'} ?? null;
        $tags->server_software = $this->{'server_software'} ?? null;
        $tags->user_agent_string = $this->{'user_agent_string'} ?? null;
        $tags->locale = $this->{'locale'} ?? null;
        $tags->configuration_storage = ($this->{'configuration_storage'} ?? '') === 'enabled'; // "enabled" or "disabled"
        $tags->php_version = $this->{'php_version'} ?? null;
        $tags->exception_type = $this->{'exception_type'} ?? null;// js or php

        return $tags;
    }

    public function getContexts(): stdClass
    {
        $contexts = new stdClass();
        $contexts->os = new stdClass();
        $contexts->os->name = $this->{'user_os'} ?? null;

        $contexts->browser = new stdClass();
        $contexts->browser->name = $this->{'browser_name'} ?? null;
        $contexts->browser->version = $this->{'browser_version'} ?? null;

        return $contexts;
    }

    public function decode(string $text): string
    {
        return htmlspecialchars_decode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @return array<string,mixed>
     */
    public function getExceptionJS(): array
    {
        $exception = new stdClass();
        $exception->type = $this->decode($this->{'exception'}->name ?? '');
        $exception->value = $this->decode($this->{'exception'}->message ?? '');
        $exception->stacktrace = new stdClass();
        $exception->stacktrace->frames = [];
        $exStack = ($this->{'exception'} ?? (object) ['stack' => []])->{'stack'} ?? [];
        foreach ($exStack as $stack) {
            $exception->stacktrace->frames[] = [
                'platform' => 'javascript',
                'function' => $this->decode($stack->{'func'} ?? ''),
                'lineno' => (int) ($stack->{'line'} ?? 0),
                'colno' => (int) ($stack->{'column'} ?? 0),
                'abs_path' => $stack->{'uri'} ?? '',
                'filename' => $stack->{'scriptname'} ?? '',
            ];
        }

        return [
            'platform' => 'javascript',
            'exception' => [
                'values' => [$exception],
            ],
            'message' => $this->decode($this->{'exception'}->message ?? ''),
            'culprit' => $this->{'script_name'} ?? $this->{'uri'} ?? null,
        ];
    }

    public function getExtras(): stdClass
    {
        return new stdClass();
    }

    public function getUserMessage(): stdClass
    {
        $userMessage = new stdClass();
        $userMessage->{'message'} = $this->decode($this->{'description'} ?? '');

        return $userMessage;
    }

    public function getUser(): stdClass
    {
        // Do not use the Ip as real data, protect the user !
        $userIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $serverSoftware = $this->{'server_software'} ?? null;
        $userAgentString = $this->{'user_agent_string'} ?? null;
        $locale = $this->{'locale'} ?? null;
        $configurationStorage = $this->{'configuration_storage'} ?? null;
        $phpVersion = $this->{'php_version'} ?? null;

        $userIp = Security::hash(
            $userIp . crc32($userIp),
            'sha256',
            true // Enable app security salt
        );// Make finding back the Ip near to impossible

        $user = new stdClass();
        // A user can be anonymously identified using the hash of the hashed IP + server software
        // + the UA + the locale + is configuration storage enabled + php version
        // Reversing the process would be near to impossible and anyway all the found data would be
        // already known and public data
        $user->id = Security::hash(
            $userIp . $serverSoftware . $userAgentString . $locale . $configurationStorage . $phpVersion,
            'sha256',
            true // Enable app security salt
        );

        $user->ip_address = '0.0.0.0';

        return $user;
    }

    private function findRoute(?string $uri): ?string
    {
        if ($uri === null) {
            return null;
        }

        $query = parse_url($uri, PHP_URL_QUERY);// foo=bar&a=b
        if (! is_string($query)) {
            return null;
        }

        $output = [];
        parse_str($query, $output);

        return $output['route'] ?? null;
    }

    public function getRoute(): ?string
    {
        if (isset($this->{'exception'})) {
            return $this->findRoute($this->{'exception'}->{'uri'} ?? null);
        }

        return null;
    }

    public function isMultiReports(): bool
    {
        return isset($this->{'exception_type'}) && $this->{'exception_type'} === 'php';
    }

    public function typeToLevel(string $type): string
    {
        switch ($type) {
            case 'Internal error':
            case 'Parsing Error':
            case 'Error':
            case 'Core Error':
                return 'error';

            case 'User Error':
            case 'User Warning':
            case 'User Notice':
                return 'info';

            case 'Warning':
            case 'Runtime Notice':
            case 'Deprecation Notice':
            case 'Notice':
            case 'Compile Warning':
                return 'warning';

            case 'Catchable Fatal Error':
                return 'tatal';

            default:
                return 'error';
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getMultiDataToSend(): array
    {
        $reports = [];
        /*
        {
            "lineNum": 272,
            "file": "./libraries/classes/Plugins/Export/ExportXml.php",
            "type": "Warning",
            "msg": "count(): Parameter must be an array or an object that implements Countable",
            "stackTrace": [
                {
                "file": "./libraries/classes/Plugins/Export/ExportXml.php",
                "line": 272,
                "function": "count",
                "args": [
                    "NULL"
                ]
                },
                {
                "file": "./export.php",
                "line": 415,
                "function": "exportHeader",
                "class": "PhpMyAdmin\\Plugins\\Export\\ExportXml",
                "type": "->"
                }
            ],
            "stackhash": "e6e0b1e1b9d90fee08a5ab8226e485fb"
        }
        */
        foreach ($this->{'errors'} as $error) {
            $exception = new stdClass();
            $exception->type = $this->decode($error->{'type'} ?? '');
            $exception->value = $this->decode($error->{'msg'} ?? '');
            $exception->stacktrace = new stdClass();
            $exception->stacktrace->frames = [];

            foreach ($error->{'stackTrace'} as $stack) {
                $trace = [
                    'platform' => 'php',
                    'function' => $stack->{'function'} ?? '',
                    'lineno' => (int) ($stack->{'line'} ?? 0),
                    'filename' => $error->{'file'} ?? null,
                ];
                if (isset($stack->{'class'})) {
                    $trace['package'] = $stack->{'class'};
                }

                if (isset($stack->{'type'})) {
                    $trace['symbol_addr'] = $stack->{'type'};
                }

                if (isset($stack->{'args'})) {// function arguments
                    $trace['vars'] = (object) $stack->{'args'};
                }

                $exception->stacktrace->frames[] = $trace;
            }

            $reports[] = [
                'platform' => 'php',
                'level' => $this->typeToLevel($error->{'type'}),
                'exception' => [
                    'values' => [$exception],
                ],
                'message' => $this->decode($error->{'msg'} ?? ''),
                'culprit' => $error->{'file'},
            ];
        }

        return $reports;
    }

    /**
     * @return array<string,mixed>
     */
    public function toJson(): array
    {
        $exType = $this->{'exception_type'} ?? 'js';

        // array_filter removes keys having null values
        $release = IncidentsTable::getStrippedPmaVersion($this->{'pma_version'} ?? '');

        return array_filter([
            'sentry.interfaces.Message' => $this->getUserMessage(),
            'release' => $release,
            'dist' => $this->{'pma_version'} ?? '',
            'platform' => $exType === 'js' ? 'javascript' : 'php',
            'timestamp' => $this->getTimestampUTC(),
            'tags' => $this->getTags(),
            'extra' => $this->getExtras(),
            'contexts' => $this->getContexts(),
            'user' => $this->getUser(),
            'transaction' => $this->getRoute(),
            'environment' => strpos($release, '-dev') === false ? 'production' : 'development',
            //TODO: 'level'
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getReports(): array
    {
        if ($this->isMultiReports()) {
            $reports = [];
            foreach ($this->getMultiDataToSend() as $data) {
                $reports[] = array_merge($this->toJson(), $data, [
                    'event_id' => $this->getEventId(),
                ]);
            }

            return $reports;
        }

        return [
            array_merge(
                $this->toJson(),
                $this->getExceptionJs(),
                [
                    'event_id' => $this->getEventId(),
                ]
            ),
        ];
    }
}
