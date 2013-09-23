<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Form\Extension;

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

use Oro\Bundle\OrganizationBundle\Form\Extension\FormTypeExtension;
use Oro\Bundle\OrganizationBundle\Form\Type\OwnershipType;
use Oro\Bundle\OrganizationBundle\Event\RecordOwnerDataListener;

class FormTypeExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $securityContext;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $configProvider;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $manager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $securityFacade;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $config;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $builder;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $user;

    private $tranlsator;

    private $organizations;

    private $businessUnits;

    private $fieldName;
    
    private $entityClassName;

    /**
     * @var FormTypeExtension
     */
    private $extension;

    public function setUp()
    {
        $this->securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $this->configProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = $this->getMockBuilder('Oro\Bundle\OrganizationBundle\Entity\Manager\BusinessUnitManager')
            ->disableOriginalConstructor()
            ->getMock();
        $businessUnitsTree = array(
            array(
                'id' => 1,
                'name' => 'Root',
                'children' => array(
                    array(
                        'id' => 2,
                        'name' => 'Child',
                    )
                )
            )
        );
        $this->manager->expects($this->any())
            ->method('getBusinessUnitsTree')
            ->will($this->returnValue($businessUnitsTree));
        $organization = $this->getMockBuilder('Oro\Bundle\OrganizationBundle\Entity\Organization')
            ->disableOriginalConstructor()
            ->getMock();
        $this->organizations = array($organization);
        $businessUnit = $this->getMockBuilder('Oro\Bundle\OrganizationBundle\Entity\BusinessUnit')
            ->disableOriginalConstructor()
            ->getMock();
        $businessUnit->expects($this->any())->method('getOrganization')->will($this->returnValue($organization));
        $this->businessUnits = array($businessUnit);
        $this->user = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\User')
            ->disableOriginalConstructor()
            ->getMock();
        $this->user->expects($this->any())->method('getId')->will($this->returnValue(1));
        $this->user->expects($this->any())->method('getBusinessUnits')->will($this->returnValue($this->businessUnits));
        $this->entityClassName = get_class($this->user);
        $this->securityFacade = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()
            ->getMock();
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->configProvider->expects($this->any())
            ->method('getConfig')
            ->with($this->entityClassName)
            ->will($this->returnValue($this->config));
        $this->configProvider->expects($this->any())
            ->method('hasConfig')
            ->with($this->entityClassName)
            ->will($this->returnValue(true));
        $token->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($this->user));
        $this->securityContext->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token));
        $config = $this->getMockBuilder('Symfony\Component\Form\FormConfigInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->any())->method('getDataClass')->will($this->returnValue($this->entityClassName));
        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->disableOriginalConstructor()
            ->getMock();
        $form->expects($this->any())->method('getConfig')->will($this->returnValue($config));
        $this->builder = $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->builder->expects($this->any())->method('getForm')->will($this->returnValue($form));
        $this->fieldName = RecordOwnerDataListener::OWNER_FIELD_NAME;
        $this->tranlsator = $this->getMockBuilder('Symfony\Component\Translation\TranslatorInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $this->extension = new FormTypeExtension(
            $this->securityContext,
            $this->configProvider,
            $this->manager,
            $this->securityFacade,
            $this->tranlsator
        );
    }

    public function testGetExtendedType()
    {
        $this->assertEquals('form', $this->extension->getExtendedType());
    }

    /**
     * Testing case with user owner type and change owner permission granted
     */
    public function testUserOwnerBuildFormGranted()
    {
        $this->mockConfigs(array('is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_USER));
        $this->builder->expects($this->once())->method('add')->with(
            $this->fieldName,
            'oro_user_select',
            array(
                'required' => true,
                'constraints' => array(new NotBlank())
            )
        );
        $this->extension->buildForm($this->builder, array());

    }

    /**
     * Testing case with user owner type and change owner permission isn't granted
     */
    public function testUserOwnerBuildFormNotGranted()
    {
        $this->mockConfigs(array('is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_USER));
        $this->builder->expects($this->never())->method('add');
        $this->extension->buildForm($this->builder, array());
    }

    /**
     * Testing case with business unit owner type and change owner permission granted
     */
    public function testBusinessUnitOwnerBuildFormGranted()
    {
        $this->mockConfigs(array('is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_BUSINESS_UNIT));
        $businessUnits = array(
            1 => "Root",
            2 => "&nbsp;&nbsp;&nbsp;Child"
        );
        $this->builder->expects($this->once())->method('add')->with(
            $this->fieldName,
            'oro_business_unit_tree_select',
            array(
                'empty_value' => '',
                'choices' => $businessUnits,
                'mapped' => true,
                'required' => true,
                'attr' => array('is_safe' => true),
                'constraints' => array(new NotBlank()),
            )
        );
        $this->extension->buildForm($this->builder, array());
    }

    /**
     * Testing case with business unit owner type and change owner permission isn't granted
     */
    public function testBusinessUnitOwnerBuildFormNotGranted()
    {
        $this->mockConfigs(array('is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_BUSINESS_UNIT));
        $this->builder->expects($this->once())->method('add')->with(
            $this->fieldName,
            'entity',
            array(
                'class' => 'OroOrganizationBundle:BusinessUnit',
                'property' => 'name',
                'choices' => $this->businessUnits,
                'mapped' => true,
                'required' => true,
                'constraints' => array(new NotBlank())
            )
        );
        $this->extension->buildForm($this->builder, array());
    }

    /**
     * Testing case with organization owner type and change owner permission granted
     */
    public function testOrganizationOwnerBuildFormGranted()
    {
        $this->mockConfigs(array('is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_ORGANIZATION));
        $this->builder->expects($this->once())->method('add')->with(
            $this->fieldName,
            'entity',
            array(
                'class' => 'OroOrganizationBundle:Organization',
                'property' => 'name',
                'mapped' => true,
                'required' => true,
                'constraints' => array(new NotBlank())
            )
        );
        $this->extension->buildForm($this->builder, array());
    }

    /**
     * Testing case with organization owner type and change owner permission isn't granted
     */
    public function testOrganizationOwnerBuildFormNotGranted()
    {
        $this->mockConfigs(array('is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_ORGANIZATION));
        $this->builder->expects($this->once())->method('add')->with(
            $this->fieldName,
            'entity',
            array(
                'class' => 'OroOrganizationBundle:Organization',
                'property' => 'name',
                'choices' => $this->organizations,
                'mapped' => true,
                'required' => true,
                'constraints' => array(new NotBlank())
            )
        );
        $this->extension->buildForm($this->builder, array());
    }

    public function testEventListener()
    {
        $this->mockConfigs(array('is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_ORGANIZATION));
        $this->builder->expects($this->once())->method('addEventListener')->will(
            $this->returnCallback(array($this, 'eventCallback'))
        );
        $this->extension->buildForm($this->builder, array());
    }

    public function eventCallback()
    {
        $args = func_get_args();
        $this->assertEquals($args[0], FormEvents::POST_SET_DATA);
        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->disableOriginalConstructor()
            ->getMock();
        $form->expects($this->any())->method('has')->will($this->returnValue(true));
        $form->expects($this->any())->method('get')->with($this->fieldName)->will($this->returnself());
        $form->expects($this->once())->method('remove')->with($this->fieldName);
        $form->expects($this->once())->method('add')->with(
            $this->fieldName,
            'text',
            array(
                'disabled' => true,
                'data' => '',
                'mapped' => false,
                'required' => false
            )
        );
        $formEvent = $this->getMockBuilder('Symfony\Component\Form\FormEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $formEvent->expects($this->any())->method('getForm')->will($this->returnValue($form));

        $formEvent->expects($this->any())->method('getData')->will($this->returnValue($this->user));
        call_user_func($args[1], $formEvent);
    }

    protected function mockConfigs(array $values)
    {
        $this->securityFacade->expects($this->any())->method('isGranted')
            ->with('ASSIGN', 'Entity:' . $this->entityClassName)
            ->will($this->returnValue($values['is_granted']));
        $this->config->expects($this->once())
            ->method('has')
            ->with('owner_type')
            ->will($this->returnValue(true));
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->with('owner_type')
            ->will($this->returnValue($values['owner_type']));
        $this->extension = new FormTypeExtension(
            $this->securityContext,
            $this->configProvider,
            $this->manager,
            $this->securityFacade,
            $this->tranlsator
        );
    }
}