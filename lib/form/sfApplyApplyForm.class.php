<?php

/**
 * Form for account apply, allows to create profile (sfGuardUserProfile) and account (sfGuardUser)
 * @author fizyk
 */
class sfApplyApplyForm extends sfGuardUserProfileForm
{
    private $validate = null;

    public function configure()
    {
        parent::configure();

        $this->removeFields();


        // Add username and password fields which we'll manage
        // on our own. Before you ask, I experimented with separately
        // emitting, merging or embedding a form subclassed from
        // sfGuardUser. It was vastly more work in every instance.
        // You have to clobber all of the other fields (you can
        // automate that, but still). If you use embedForm you realize
        // you've got a nested form that looks like a
        // nested form and an end user looking at that and
        // saying "why?" If you use mergeForm you can't save(). And if
        // you output the forms consecutively you have to manage your
        // own transactions. Adding two fields to the profile form
        // is definitely simpler.


        //Setting username widget
        $this->setWidget( 'username',
                new sfWidgetFormInput( array(), array( 'maxlength' => 16 ) ) );
        $this->widgetSchema->moveField('username', sfWidgetFormSchema::FIRST);

        //Setting password widgets
        $this->setWidget( 'password', 
                new sfWidgetFormInputPassword( array(), array('maxlength' => 128) ) );
        $this->widgetSchema->moveField('password', sfWidgetFormSchema::AFTER, 'username');

        $this->setWidget('password2', 
                new sfWidgetFormInputPassword( array(), array('maxlength' => 128) ) );
        $this->widgetSchema->moveField('password2', sfWidgetFormSchema::AFTER, 'password');

        //Settings for email fields
        $email = $this->getWidget('email');
        $class = get_class($email);
        $this->setWidget('email2', 
                new $class( array(), array('maxlength' => $email->getAttribute('maxlength') )
        ) );
        $this->widgetSchema->moveField( 'email2', sfWidgetFormSchema::AFTER, 'email' );

        //Firstname and lastname
        $this->setWidget( 'firstname', new sfWidgetFormInputText( array(), array( 'maxlength' => 30 ) ) );
        $this->setWidget( 'lastname', new sfWidgetFormInputText( array(), array( 'maxlength' => 70 ) ) );

        $this->widgetSchema->setLabels( array(
            'username' => 'Username',
            'password' => 'Password',
            'password2' => 'Confirm password',
            'email' => 'Email address',
            'email2' => 'Confirm email',
            'firstname' => 'First Name',
            'lastname' => 'Last name'
        ) );

        $this->widgetSchema->setNameFormat('sfApplyApply[%s]');
        $this->widgetSchema->setFormFormatterName('list');

        // We have the right to an opinion on these fields because we
        // implement at least part of their behavior. Validators for the
        // rest of the user profile come from the schema and from the
        // developer's form subclass

        $this->setValidator( 'username', new sfValidatorApplyUsername() );

        $this->setValidator( 'password', new sfValidatorApplyPassword() );
        $this->setValidator( 'password2', new sfValidatorApplyPassword() );

        // Be aware that sfValidatorEmail doesn't guarantee a string that is preescaped for HTML purposes.
        // If you choose to echo the user's email address somewhere, make sure you escape entities.
        // <, > and & are rare but not forbidden due to the "quoted string in the local part" form of email address
        // (read the RFC if you don't believe me...).

        $this->setValidator('email', new sfValidatorAnd( array(
            new sfValidatorEmail( array('required' => true, 'trim' => true) ),
            new sfValidatorString( array('required' => true, 'max_length' => 80) ),
            new sfValidatorDoctrineUnique(
                    array( 'model' => 'sfGuardUserProfile', 'column' => 'email'),
                    array('invalid' => 'An account with that email address already exists. If you have forgotten your password, click "cancel", then "Reset My Password."') )
        )));

        $this->setValidator('email2', new sfValidatorEmail( 
                array( 'required' => true, 'trim' => true )));

        
        $this->setValidator('firstname', new sfValidatorApplyFirstname() );
        
        $this->setValidator('lastname', new sfValidatorApplyLastname() );

        $schema = $this->validatorSchema;

        // Hey Fabien, adding more postvalidators is kinda verbose!
        $postValidator = $schema->getPostValidator();

        $postValidators = array( 
            new sfValidatorSchemaCompare( 'password', sfValidatorSchemaCompare::EQUAL,
                    'password2', array(), array('invalid' => 'The passwords did not match.') ),
            new sfValidatorSchemaCompare( 'email', sfValidatorSchemaCompare::EQUAL,
                    'email2', array(), array('invalid' => 'The email addresses did not match.') ) );

        if( $postValidator )
        {
            $postValidators[] = $postValidator;
        }

        //Include captcha if enabled
        if ($this->isCaptchaEnabled() )
        {
            $this->addCaptcha();
        }

        $this->validatorSchema->setPostValidator( new sfValidatorAnd($postValidators) );
    }
    
    public function setValidate( $validate )
    {
        $this->validate = $validate;
    }
  
    public function doSave($con = null)
    {
        $user = new sfGuardUser();
        $user->setUsername($this->getValue('username'));
        $user->setPassword($this->getValue('password'));
        // They must confirm their account first
        $user->setIsActive(false);
        $user->save();
        $this->userId = $user->getId();

        return parent::doSave($con);
    }

    public function updateObject($values = null)
    {
        $object = parent::updateObject($values);
        $object->setUserId($this->userId);
        $object->setValidate($this->validate);

        // Don't break subclasses!
        return $object;
    }

    protected function isCaptchaEnabled()
    {
        return sfConfig::get('app_recaptcha_enabled');
    }

    protected function addCaptcha()
    {
        $this->widgetSchema['captcha'] = new sfWidgetFormReCaptcha(array(
          'public_key' => sfConfig::get('app_recaptcha_public_key')
        ));

        $this->validatorSchema['captcha'] = new sfValidatorReCaptcha(array(
          'private_key' => sfConfig::get('app_recaptcha_private_key')
        ));
        $this->validatorSchema['captcha']
            ->setMessage('captcha', __('The captcha is not valid (%error%).', array(), 'sfForkedApply'))
            ->setMessage('server_problem', __('Unable to check the captcha from the server (%error%).', array(), 'sfForkedApply'));
    }


    protected function removeFields()
    {

        // We're making a new user or editing the user who is
        // logged in. In neither case is it appropriate for
        // the user to get to pick an existing userid. The user
        // also doesn't get to modify the validate field which
        // is part of how their account is verified by email.

        unset($this['user_id'], $this['validate'], $this['validate_at'],
                $this['created_at'], $this['updated_at'], $this['email_new']);

    }

}

