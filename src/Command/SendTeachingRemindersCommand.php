<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\OfferingInterface;
use App\Entity\SchoolInterface;
use App\Entity\UserInterface;
use App\Repository\OfferingRepository;
use App\Repository\SchoolRepository;
use App\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Sends teaching reminders to educators for their upcoming session offerings.
 *
 * Class SendTeachingRemindersCommand
 */
class SendTeachingRemindersCommand extends Command
{
    public const DEFAULT_TEMPLATE_NAME = 'teachingreminder.text.twig';

    public const DEFAULT_MESSAGE_SUBJECT = 'Upcoming Teaching Session';

    protected OfferingRepository $offeringRepository;

    protected SchoolRepository $schoolRepository;

    protected Environment $twig;

    protected MailerInterface $mailer;

    protected Config $config;

    protected Filesystem $fs;

    protected string $kernelProjectDir;

    /**
     * @param OfferingRepository $offeringRepository
     * @param SchoolRepository $schoolRepository
     * @param Environment $twig
     * @param MailerInterface $mailer
     * @param Config $config
     * @param Filesystem $fs
     * @param string $kernelProjectDir
     */
    public function __construct(
        OfferingRepository $offeringRepository,
        SchoolRepository $schoolRepository,
        Environment $twig,
        MailerInterface $mailer,
        Config $config,
        Filesystem $fs,
        string $kernelProjectDir
    ) {
        parent::__construct();
        $this->offeringRepository = $offeringRepository;
        $this->schoolRepository = $schoolRepository;
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->config = $config;
        $this->fs = $fs;
        $this->kernelProjectDir = $kernelProjectDir;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ilios:send-teaching-reminders')
            ->setAliases(['ilios:messaging:send-teaching-reminders'])
            ->setDescription('Sends teaching reminders to educators.')
            ->addArgument(
                'sender',
                InputArgument::REQUIRED,
                'Email address to send reminders from.'
            )
            ->addArgument(
                'base_url',
                InputArgument::REQUIRED,
                'The base URL of your Ilios instance.'
            )
            ->addOption(
                'days',
                null,
                InputOption::VALUE_OPTIONAL,
                'How many days in advance of teaching events reminders should be sent.',
                7
            )
            ->addOption(
                'subject',
                null,
                InputOption::VALUE_OPTIONAL,
                'The subject line of reminder emails.',
                self::DEFAULT_MESSAGE_SUBJECT
            )
            ->addOption(
                'sender_name',
                null,
                InputOption::VALUE_OPTIONAL,
                "The name of the reminder's sender."
            )
            ->addOption(
                'schools',
                null,
                InputOption::VALUE_OPTIONAL,
                "Only Send Reminders for this comma seperated list of school ids."
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Prints out notification instead of emailing it. Useful for testing/debugging purposes.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // input validation
        $errors = $this->validateInput($input);
        if (! empty($errors)) {
            foreach ($errors as $error) {
                $output->writeln("<error>{$error}</error>");
            }
            return 1;
        }

        $daysInAdvance = (int) $input->getOption('days');
        $sender = $input->getArgument('sender');
        $baseUrl = rtrim($input->getArgument('base_url'), '/');
        $subject = $input->getOption('subject');
        $isDryRun = $input->getOption('dry-run');
        $senderName = $input->getOption('sender_name');
        $schools = $input->getOption('schools');
        $from = $sender;
        if ($senderName) {
            $from = new Address($sender, $senderName);
        }
        if ($schools) {
            $schoolIds = array_map('intval', str_getcsv($schools));
        } else {
            $schoolIds = $this->schoolRepository->getIds();
        }

        // get all applicable offerings.
        $offerings = $this->offeringRepository->getOfferingsForTeachingReminders($daysInAdvance, $schoolIds);

        if (!count($offerings)) {
            $output->writeln('<info>No offerings with pending teaching reminders found.</info>');
            return 0;
        }

        // mail out a reminder per instructor per offering.
        $templateCache = [];
        $i = 0;

        /** @var OfferingInterface $offering */
        foreach ($offerings as $offering) {
            $deleted = ! $offering->getSession()
                || ! $offering->getSession()->getCourse()
                || ! $offering->getSchool();

            if ($deleted) {
                continue;
            }

            $school = $offering->getSchool();
            if (! array_key_exists($school->getId(), $templateCache)) {
                $template = $this->getTemplatePath($school);
                $templateCache[$school->getId()] = $template;
            }
            $template = $templateCache[$school->getId()];

            $instructors = $offering->getAllInstructors()->toArray();
            $timezone = $this->config->get('timezone');


            /** @var UserInterface $instructor */
            foreach ($instructors as $instructor) {
                $i++;
                $messageBody = $this->twig->render($template, [
                    'base_url' => $baseUrl,
                    'instructor' => $instructor,
                    'offering' => $offering,
                    'timezone' => $timezone
                ]);
                $email = $instructor->getPreferredEmail();
                if (empty($email)) {
                    $email = $instructor->getEmail();
                }
                $message = (new Email())
                    ->from($from)
                    ->to($email)
                    ->subject($subject)
                    ->text($messageBody);
                if ($isDryRun) {
                    $output->writeln($message->getHeaders()->toString());
                    $output->writeln($message->getTextBody());
                } else {
                    $this->mailer->send($message);
                }
            }
        }

        $output->writeln("<info>Sent {$i} teaching reminders.</info>");

        return 0;
    }

    /**
     * Locates the applicable message template for a given school and returns its path.
     * @param SchoolInterface $school
     * @return string The template path.
     */
    protected function getTemplatePath(SchoolInterface $school)
    {
        $prefix = $school->getTemplatePrefix();
        if ($prefix) {
            $path = 'email/' . basename($prefix . '_' . self::DEFAULT_TEMPLATE_NAME);
            if ($this->fs->exists($this->kernelProjectDir . '/custom/templates/' . $path)) {
                return $path;
            }
        }

        return 'email/' . self::DEFAULT_TEMPLATE_NAME;
    }

    /**
     * Validates user input.
     *
     * @param InputInterface $input
     * @return array A list of validation error message. Empty if no validation errors occurred.
     */
    protected function validateInput(InputInterface $input)
    {
        $errors = [];

        $daysInAdvance = intval($input->getOption('days'), 10);
        if (0 > $daysInAdvance) {
            $errors[] = "Invalid value '{$daysInAdvance}' for '--days' option. Must be greater or equal to 0.";
        }
        $sender = $input->getArgument('sender');
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid value '{$sender}' for '--sender' option. Must be a valid email address.";
        }

        return $errors;
    }
}
