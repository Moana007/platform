<?php

namespace Oro\Bundle\EmailBundle\Tests\Functional\DataFixtures;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

use Oro\Bundle\EmailBundle\Model\FolderType;
use Oro\Bundle\EmailBundle\Mailer\Processor;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Entity\Email;

class LoadEmailData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    /**
     * @var array
     */
    protected $templates;

    /**
     * @var EmailEntityBuilder
     */
    protected $emailEntityBuilder;

    /**
     * @var Processor
     */
    protected $mailerProcessor;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return ['Oro\Bundle\EmailBundle\Tests\Functional\DataFixtures\LoadUserData',];
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        if (!$container) {
            return;
        }

        $this->container = $container;
        $this->emailEntityBuilder = $container->get('oro_email.email.entity.builder');
        $this->mailerProcessor = $container->get('oro_email.mailer.processor');
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $om)
    {
        $this->loadEmailTemplates();
        $this->loadEmailsDemo($om);
        $om->flush();
    }

    protected function loadEmailTemplates()
    {
        $dictionaryDir = $this->container
            ->get('kernel')
            ->locateResource('@OroEmailBundle/Tests/Functional/DataFixtures/Data');

        $handle = fopen($dictionaryDir . DIRECTORY_SEPARATOR. "emails.csv", "r");
        if ($handle) {
            $headers = [];
            if (($data = fgetcsv($handle, 1000, ",")) !== false) {
                //read headers
                $headers = $data;
            }
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $this->templates[] = array_combine($headers, array_values($data));
            }
        }
    }

    /**
     * @param ObjectManager $om
     */
    protected function loadEmailsDemo(ObjectManager $om)
    {
        foreach ($this->templates as $index => $template) {
            $owner = $this->getReference('simple_user');
            $origin = $this->mailerProcessor->getEmailOrigin($owner->getEmail());

            $email = $this->emailEntityBuilder->email(
                $template['Subject'],
                $owner->getEmail(),
                $owner->getEmail(),
                new \DateTime('now'),
                new \DateTime('now'),
                new \DateTime('now'),
                Email::NORMAL_IMPORTANCE,
                "cc{$index}@example.com",
                "bcc{$index}@example.com"
            );

            $email->addFolder($origin->getFolder(FolderType::SENT));

            $emailBody = $this->emailEntityBuilder->body(
                "Hi,\n" . $template['Text'],
                false,
                true
            );
            $email->setEmailBody($emailBody);
            $email->setMessageId(sprintf('id.%s@%s', uniqid(), '@bap.migration.generated'));
            $this->setReference('email_' . ($index + 1), $email);
            $this->setReference('emailBody_' . ($index + 1), $emailBody);
        }
        $this->emailEntityBuilder->getBatch()->persist($om);
    }
}
