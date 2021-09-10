<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\DataObject;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;
use Xigen\CacheWarmer\Helper\Config;
use Xigen\CacheWarmer\Logger\Logger;
use Xigen\CacheWarmer\Model\WarmFactory;

class Runner extends Command
{
    const STORE_OPTION = 'store';
    const LOG_ARGUMENT = 'log';
    const WARM_ARGUMENT = 'warm';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ProgressBarFactory
     */
    protected $progressBarFactory;

    /**
     * @var WarmFactory
     */
    protected $warmFactory;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logger
     */
    protected $customLogger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var StoreManagerInterface
     */
    protected $store = null;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Symfony\Component\Console\Helper\ProgressBarFactory $progressBarFactory
     * @param \Xigen\CacheWarmer\Model\WarmFactory $warmFactory
     * @param \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Xigen\CacheWarmer\Helper\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Xigen\CacheWarmer\Logger\Logger $customLogger
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ProgressBarFactory $progressBarFactory,
        WarmFactory $warmFactory,
        CurlFactory $curlFactory,
        Curl $curl,
        Config $config,
        StoreManagerInterface $storeManager,
        Logger $customLogger
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->progressBarFactory = $progressBarFactory;
        $this->warmFactory = $warmFactory;
        $this->curlFactory = $curlFactory;
        $this->curl = $curl;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->customLogger = $customLogger;
        parent::__construct();
    }

    /**
     * Executes the current command.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null null or 0 if everything went fine, or an error code
     * @throws LogicException When this abstract method is not implemented
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->state->setAreaCode(Area::AREA_GLOBAL);

        $warm = $this->input->getArgument(self::WARM_ARGUMENT) ?: false;
        $storeId = $this->input->getOption(self::STORE_OPTION) ?: 1;
        $logToFile = $this->input->getArgument(self::LOG_ARGUMENT) ?: false;

        if ($warm) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                (string) __('You are about to initiate cache warmer. Are you sure? <comment>[y/N]</comment>'),
                false
            );

            if (!$helper->ask($this->input, $this->output, $question) && $this->input->isInteractive()) {
                return Cli::RETURN_FAILURE;
            }

            $stopwatch = new Stopwatch();
            $stopwatch->start('cache_warm_runner');

            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Start');

            $build = $this->warmFactory
                ->create()
                ->setStoreId($storeId);

            /** @var ProgressBar $progress */
            $progress = $this->progressBarFactory->create(
                [
                    'output' => $this->output,
                    'max' => count($build->getUrls())
                ]
            );

            $progress->setFormat(
                "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| %message%"
            );

            if ($this->output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
                $progress->setOverwrite(false);
            }

            $progress->start();

            $this->store = $this->storeManager->getStore($storeId);

            // some servers block blank useragent
            $userAgent = $this->prepareObject();
            $proxy = $this->config->getProxy($this->store);

            foreach ($build->getUrls() as $url) {
                $result = (string) __('%1', $this->fetchUrl($url, $userAgent, $proxy));
                if ($logToFile) {
                    $this->customLogger->info($result);
                }
                $progress->setMessage($result);
                $progress->advance();
            }

            $progress->finish();
            $this->output->writeln('');
            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Finish');

            $event = $stopwatch->stop('cache_warm_runner');

            $this->output->writeln((string) $event);
            $this->output->writeln((string) __("Start : %1", date("d-m-Y H:i:s", (int) ($event->getOrigin() / 1000))));
            $this->output->writeln((string) __(
                "End : %1",
                date("d-m-Y H:i:s", (int) (($event->getOrigin() + $event->getEndTime()) / 1000))
            ));
            $this->output->writeln((string) __("Memory : %1 MiB", $event->getMemory() / 1024 / 1024));
        }
    }

    /**
     * Fetch URL
     * @param string $url
     * @param DataObject $userAgent
     * @param null|bool $proxy
     * @return string
     */
    public function fetchUrl($url, DataObject $userAgent, $proxy = null)
    {
        try {
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->curl->setOption(CURLOPT_TIMEOUT, 5);
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, 0);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, 0);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, 1);
            $this->curl->setOption(CURLOPT_FOLLOWLOCATION, 1);
            $this->curl->setOption(CURLOPT_MAXREDIRS, 5);
            $this->curl->setOption(CURLOPT_USERAGENT, (string) __(
                "%1/%2 (%3)",
                $userAgent->getName(),
                $userAgent->getVersion(),
                $userAgent->getEdition()
            ));
            if ($this->output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
                $this->curl->setOption(CURLOPT_VERBOSE, 1);
            }

            if ($proxy) {
                $this->curl->setOption(CURLOPT_HTTPPROXYTUNNEL, 1);
                $this->curl->setOption(CURLOPT_PROXY, $proxy);
            }
            $this->curl->get($url);
            $response = $this->curl->getBody();
            $reason = null;
            if ($response === false) {
                $reason = (string) __('Empty response.');
            } else {
                $responseCode = \Zend_Http_Response::extractCode($response);
                if (in_array($responseCode, $this->getResponseCodes())) {
                    $reason = (string) __('Response code: %1.', $responseCode);
                }
            }

            if ($reason) {
                throw new \Exception($reason); // phpcs:ignore
            }
        } catch (\Exception $e) { // phpcs:ignore
            $this->logger->critical($e);
            return (string) __(
                "<error>[Failure]</error> <question>[Result] %2</question> <comment>[URL] %1</comment>",
                $url,
                $e->getMessage()
            );
        }

        return (string) __("<info>[Success]</info> <comment>[URL] %1</comment>", $url);
    }

    /**
     * Response codes to watch for
     * @return array
     */
    protected function getResponseCodes()
    {
        return [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
            Response::HTTP_METHOD_NOT_ALLOWED,
            Response::HTTP_REQUEST_TIMEOUT,
            Response::HTTP_GONE,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            Response::HTTP_BAD_GATEWAY,
            Response::HTTP_SERVICE_UNAVAILABLE,
            Response::HTTP_GATEWAY_TIMEOUT,
        ];
    }

    /**
     * Prepare user agent object
     * @return \Magento\Framework\DataObject
     */
    protected function prepareObject()
    {
        $object = new DataObject();
        $object->setName($this->config->getAgentName($this->store));
        $object->setVersion($this->config->getAgentVersion($this->store));
        $object->setEdition($this->config->getAgentEdition($this->store));
        return $object;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName("xigen:cachewarmer:runner");
        $this->setDescription("Warm the cache");
        $this->setDefinition([
            new InputArgument(self::WARM_ARGUMENT, InputArgument::REQUIRED, 'Warm'),
            new InputOption(self::STORE_OPTION, "-s", InputOption::VALUE_REQUIRED, "Store ID"),
            new InputArgument(self::LOG_ARGUMENT, InputArgument::OPTIONAL, "Log")
        ]);
        parent::configure();
    }
}
