<?php

namespace Dynamic\Profiles\Page;

use Dynamic\Profiles\Form\ProfileForm;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class MemberProfileController extends \PageController
{
    /**
     * @var array
     */
    private static $allowed_actions = array(
        'index',
        'view',
        'update',
        'register',
        'ProfileForm',
    );

    /**
     * @var
     */
    private $profile;

    /**
     * @return mixed
     */
    public function getProfile()
    {
        if (!$this->profile) {
            $this->setProfile();
        }

        return $this->profile;
    }

    /**
     * @param Member|null $member
     *
     * @return $this
     */
    public function setProfile(Member $member = null)
    {
        if ($member !== null && $member instanceof Member) {
            $this->profile = $member;

            return $this;
        }
        if (!$this->profile && Security::getCurrentUser()) {
            $id = ($this->request->latestParam('ProfileID'))
                ? $this->request->latestParam('ProfileID')
                : Security::getCurrentUser()->ID;
            $this->profile = Member::get()->byID($id);

            return $this;
        }

        return $this;
    }

    /**
     * @param HTTPRequest $request
     * @return $this|\SilverStripe\Control\HTTPResponse
     */
    public function index(HTTPRequest $request)
    {
        if (!$member = Security::getCurrentUser()) {
            return $this->redirect($this->Link() . 'register/');
        } else {
            return $this;
        }
    }

    /**
     * @param HTTPRequest $request
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function view(HTTPRequest $request)
    {
        if (!$request->latestParam('ID')) {
            return $this->httpError(404);
        }

        if (!$this->getProfile()) {
            $this->setProfile();
        }

        if ($profile = $this->getProfile()) {
            //redirect to /profile if they're trying to view their own
            //todo implement view public profile feature
            if ($profile->ID == Security::getCurrentUser()->ID) {
                $this->redirect('/profile');
            }

            return $this->renderWith(
                array(
                    'ProfilePage',
                    'Page',
                ),
                array(
                    'Profile' => $profile,
                )
            );
        }

        //ProfileErrorEmail::send_email(Member::currentUserID());
        return $this->httpError(404, "Your profile isn't available at this time. A our developers have been notified.");
    }

    public function update(HTTPRequest $request)
    {
        if (!$this->getProfile()) {
            $this->setProfile(Security::getCurrentUser());
        }
        if ($member = $this->getProfile()) {
            $form = $this->ProfileForm();
            $fields = $form->Fields();
            $fields->dataFieldByName('Password')->setCanBeEmpty(true);
            return $this->customise(
                array(
                    'Title' => 'Update your Profile',
                    'Form' => $form->loadDataFrom($member),
                )
            );
        }
        //ProfileErrorEmail::send_email(Member::currentUserID());
        //todo determine proper error handling
        return Security::permissionFailure($this, 'Please login to update your profile.');
    }

    /**
     * @param HTTPRequest $request
     * @return \SilverStripe\Control\HTTPResponse|\SilverStripe\View\ViewableData_Customised
     */
    public function register(HTTPRequest $request)
    {
        if (!Security::getCurrentUser()) {
            $content = DBField::create_field('HTMLText', '<p>Create a profile.</p>');

            return $this->customise(
                array(
                    'Title' => 'Sign Up',
                    'Content' => $content,
                    'Form' => self::ProfileForm(),
                )
            );
        }

        return $this->redirect($this->Link());
    }

    /**
     * @return ProfileForm
     */
    public function ProfileForm()
    {
        $form = ProfileForm::create($this, __FUNCTION__)
            ->setFormAction(Controller::join_links($this->Link(), __FUNCTION__));
        if ($form->hasExtension('FormSpamProtectionExtension')) {
            $form->enableSpamProtection();
        }

        return $form;
    }

    /**
     * @param $data
     * @param ProfileForm $form
     * @return \SilverStripe\Control\HTTPResponse
     */
    public function processmember($data, ProfileForm $form)
    {
        $member = Member::create();
        $form->saveInto($member);
        $public = Group::get()
            ->filter(array('Code' => 'public'))
            ->first();
        if ($member->write()) {
            $groups = $member->Groups();
            $groups->add($public);
            $member->login();

            return $this->redirect($this->Link());
        }

        //RegistrationErrorEmail::send_email(Member::currentUserID());
        //todo figure out proper error handling
        return $this->httpError(404);
    }
}