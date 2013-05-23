<?php

namespace Objects\MongoDBUserBundle\Document;

use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 * @Unique(fields={"loginName"}, groups={"signup", "loginName"})
 * @Unique(fields={"email"}, groups={"signup", "edit", "email"})
 */
class User implements AdvancedUserInterface {

    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\Hash
     */
    private $roles = array();

    /**
     * @MongoDB\String
     * @Assert\NotBlank(groups={"signup", "loginName"})
     * @Assert\Regex(pattern="/^\w+$/u", groups={"loginName"}, message="Only characters, numbers and _")
     */
    private $loginName;

    /**
     * @MongoDB\String
     * @Assert\Email(groups={"signup", "edit", "email"})
     */
    private $email;

    /**
     * @MongoDB\String
     */
    private $password;

    /**
     * @Assert\Length(min=6, groups={"signup", "edit", "password"})
     * @Assert\NotBlank(groups={"signup", "password"})
     */
    private $userPassword;

    /**
     * @Assert\NotBlank(groups={"oldPassword"})
     * @SecurityAssert\UserPassword(groups={"oldPassword"})
     */
    private $oldPassword;

    /**
     * @MongoDB\String
     */
    private $confirmationCode;

    /**
     * @MongoDB\Date
     */
    private $createdAt;

    /**
     * @MongoDB\String
     */
    private $firstName;

    /**
     * @MongoDB\String
     */
    private $lastName;

    /**
     * @MongoDB\String
     */
    private $about;

    /**
     * 0 female, 1 male
     * @MongoDB\Boolean
     */
    private $gender;

    /**
     * @MongoDB\Boolean
     */
    private $locked = false;

    /**
     * @MongoDB\Boolean
     */
    private $enabled = true;

    /**
     * @MongoDB\String
     */
    private $salt;

    /**
     * @MongoDB\String
     */
    private $image;

    /**
     * a temp variable for storing the old image name to delete the old image after the update
     */
    private $temp;

    /**
     * @Assert\Image(groups={"image", "edit"})
     * @var \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    private $file;

    /**
     * Set image
     *
     * @param string $image
     * @return User
     */
    public function setImage($image) {
        $this->image = $image;
        return $this;
    }

    /**
     * Get image
     *
     * @return string
     */
    public function getImage() {
        return $this->image;
    }

    /**
     * Set file
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @return User
     */
    public function setFile($file) {
        $this->file = $file;
        //check if we have an old image
        if ($this->image) {
            //store the old name to delete on the update
            $this->temp = $this->image;
            $this->image = NULL;
        } else {
            $this->image = 'initial';
        }
        return $this;
    }

    /**
     * Get file
     *
     * @return \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    public function getFile() {
        return $this->file;
    }

    /**
     * this function is used to delete the current image
     * the deleting of the current object will also delete the image and you do not need to call this function
     * if you call this function before you remove the object the image will not be removed
     */
    public function removeImage() {
        //check if we have an old image
        if ($this->image) {
            //store the old name to delete on the update
            $this->temp = $this->image;
            //delete the current image
            $this->image = NULL;
        }
    }

    /**
     * @MongoDB\PrePersist()
     * @MongoDB\PreUpdate()
     */
    public function preUpload() {
        if (NULL !== $this->file && (NULL === $this->image || 'initial' === $this->image)) {
            //get the image extension
            $extension = $this->file->guessExtension();
            //generate a random image name
            $img = uniqid();
            //get the image upload directory
            $uploadDir = $this->getUploadRootDir();
            //check if the upload directory exists
            if (!@is_dir($uploadDir)) {
                //get the old umask
                $oldumask = umask(0);
                //not a directory probably the first time try to create the directory
                $success = @mkdir($uploadDir, 0755, TRUE);
                //reset the umask
                umask($oldumask);
                //check if we created the folder
                if (!$success) {
                    //could not create the folder throw an exception to stop the insert
                    throw new \Exception("Can not create the directory $uploadDir");
                }
            }
            //check that the file name does not exist
            while (@file_exists("$uploadDir/$img.$extension")) {
                //try to find a new unique name
                $img = uniqid();
            }
            //set the image new name
            $this->image = "$img.$extension";
        }
    }

    /**
     * @MongoDB\PostPersist()
     * @MongoDB\PostUpdate()
     */
    public function upload() {
        if (NULL !== $this->file) {
            // you must throw an exception here if the file cannot be moved
            // so that the entity is not persisted to the database
            // which the UploadedFile move() method does
            $this->file->move($this->getUploadRootDir(), $this->image);
            //remove the file as you do not need it any more
            $this->file = NULL;
        }
        //check if we have an old image
        if ($this->temp) {
            //try to delete the old image
            @unlink($this->getUploadRootDir() . '/' . $this->temp);
            //clear the temp image
            $this->temp = NULL;
        }
    }

    /**
     * @MongoDB\PostRemove()
     */
    public function postRemove() {
        //check if we have an image
        if ($this->image) {
            //try to delete the image
            @unlink($this->getAbsolutePath());
        }
    }

    /**
     * @return string the path of image starting of root
     */
    public function getAbsolutePath() {
        return $this->getUploadRootDir() . '/' . $this->image;
    }

    /**
     * @return string the relative path of image starting from web directory
     */
    public function getWebPath() {
        return NULL === $this->image ? NULL : '/' . $this->getUploadDir() . '/' . $this->image;
    }

    /**
     * @return string the path of upload directory starting of root
     */
    public function getUploadRootDir() {
        // the absolute directory path where uploaded documents should be saved
        return __DIR__ . '/../../../../web/' . $this->getUploadDir();
    }

    /**
     * @param $width the desired image width
     * @param $height the desired image height
     * @return string the htaccess file url pattern which map to timthumb url
     */
    public function getSmallImageUrl($width = 50, $height = 50) {
        return NULL === $this->image ? NULL : "/user-profile-image/$width/$height/$this->image";
    }

    /**
     * @return string the document upload directory path starting from web folder
     */
    private function getUploadDir() {
        return 'images/users-profiles-images';
    }

    /**
     * initialize the main default attributes
     */
    public function __construct() {
        $this->createdAt = new \DateTime();
        $this->confirmationCode = md5(uniqid(rand()));
        $this->salt = md5(time());
    }

    /**
     * @return string the object name
     */
    public function __toString() {
        if ($this->lastName) {
            return "$this->firstName $this->lastName";
        }
        return (string) $this->firstName;
    }

    /**
     * this function will set a valid random password for the user
     * @return User
     */
    public function setRandomPassword() {
        $this->setUserPassword(rand());
        return $this;
    }

    /**
     * set the first name for the user
     * @MongoDB\PrePersist()
     * @return User
     */
    public function setValidFirstName() {
        if (!$this->firstName) {
            $this->setFirstName($this->getUsername());
        }
        return $this;
    }

    /**
     * this function will set the valid password for the user
     * @MongoDB\PrePersist()
     * @MongoDB\PreUpdate()
     * @return User
     */
    public function setValidPassword() {
        //check if we have a password
        if ($this->getUserPassword()) {
            //hash the password
            $this->setPassword($this->hashPassword($this->getUserPassword()));
        } else {
            //check if the object is new
            if ($this->getId() === NULL) {
                //new object set a random password
                $this->setRandomPassword();
                //hash the password
                $this->setPassword($this->hashPassword($this->getUserPassword()));
            }
        }
        return $this;
    }

    /**
     * this function will hash a password and return the hashed value
     * the encoding has to be the same as the one in the project security.yml file
     * @param string $password the password to return it is hash
     */
    private function hashPassword($password) {
        //create an encoder object
        $encoder = new MessageDigestPasswordEncoder('sha512', true, 10);
        //return the hashed password
        return $encoder->encodePassword($password, $this->getSalt());
    }

    /**
     * Set userPassword
     *
     * @param string $password
     * @return User
     */
    public function setUserPassword($password) {
        $this->userPassword = $password;
        $this->password = null;
        return $this;
    }

    /**
     * @return string the user password
     */
    public function getUserPassword() {
        return $this->userPassword;
    }

    /**
     * Implementation of getRoles for the UserInterface.
     *
     * @return array An array of Roles
     */
    public function getRoles() {
        return $this->roles;
    }

    /**
     * Implementation of eraseCredentials for the UserInterface.
     */
    public function eraseCredentials() {
        //remove the user password
        $this->userPassword = null;
        $this->oldPassword = null;
    }

    /**
     * Implementation of getPassword for the UserInterface.
     * @return string the hashed user password
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * Implementation of getSalt for the UserInterface.
     * @return string the user salt
     */
    public function getSalt() {
        return $this->salt;
    }

    /**
     * Implementation of getUsername for the UserInterface.
     * check security.yml to know the used column by the firewall
     * @return string the user name used by the firewall configurations.
     */
    public function getUsername() {
        return $this->loginName;
    }

    /**
     * Implementation of isAccountNonExpired for the AdvancedUserInterface.
     * @return boolean
     */
    public function isAccountNonExpired() {
        return TRUE;
    }

    /**
     * Implementation of isCredentialsNonExpired for the AdvancedUserInterface.
     * @return boolean
     */
    public function isCredentialsNonExpired() {
        return TRUE;
    }

    /**
     * Implementation of isAccountNonLocked for the AdvancedUserInterface.
     * @return boolean
     */
    public function isAccountNonLocked() {
        return !$this->locked;
    }

    /**
     * Implementation of isEnabled for the AdvancedUserInterface.
     * @return boolean
     */
    public function isEnabled() {
        return $this->enabled;
    }

    /**
     * Set loginName
     *
     * @param string $loginName
     * @return User
     */
    public function setLoginName($loginName) {
        $this->loginName = $loginName;
        return $this;
    }

    /**
     * Get loginName
     *
     * @return string
     */
    public function getLoginName() {
        return $this->loginName;
    }

    /**
     * this function will return the string representing the user gender
     * @return string gender type
     */
    public function getGenderString($locale = 'en') {
        $gendersArray = $this->getGendersArray($locale);
        if ($this->gender === 0 || $this->gender === 1) {
            return $gendersArray[$this->gender];
        }
        return '';
    }

    /**
     * @author Mahmoud
     * @param string $locale
     */
    public function getGendersArray($locale = 'en') {
        return array(
            0 => 'Female',
            1 => 'Male'
        );
    }

    /**
     * Get id
     *
     * @return id $id
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return User
     */
    public function setPassword($password) {
        $this->password = $password;
        return $this;
    }

    /**
     * Set confirmationCode
     *
     * @param string $confirmationCode
     * @return User
     */
    public function setConfirmationCode($confirmationCode) {
        $this->confirmationCode = $confirmationCode;
        return $this;
    }

    /**
     * Get confirmationCode
     *
     * @return string $confirmationCode
     */
    public function getConfirmationCode() {
        return $this->confirmationCode;
    }

    /**
     * Set createdAt
     *
     * @param date $createdAt
     * @return User
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get createdAt
     *
     * @return date $createdAt
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }

    /**
     * Set firstName
     *
     * @param string $firstName
     * @return User
     */
    public function setFirstName($firstName) {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * Get firstName
     *
     * @return string $firstName
     */
    public function getFirstName() {
        return $this->firstName;
    }

    /**
     * Set lastName
     *
     * @param string $lastName
     * @return User
     */
    public function setLastName($lastName) {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * Get lastName
     *
     * @return string $lastName
     */
    public function getLastName() {
        return $this->lastName;
    }

    /**
     * Set about
     *
     * @param string $about
     * @return User
     */
    public function setAbout($about) {
        $this->about = $about;
        return $this;
    }

    /**
     * Get about
     *
     * @return string $about
     */
    public function getAbout() {
        return $this->about;
    }

    /**
     * Set gender
     *
     * @param boolean $gender
     * @return User
     */
    public function setGender($gender) {
        $this->gender = $gender;
        return $this;
    }

    /**
     * Get gender
     *
     * @return boolean $gender
     */
    public function getGender() {
        return $this->gender;
    }

    /**
     * Set locked
     *
     * @param boolean $locked
     * @return User
     */
    public function setLocked($locked) {
        $this->locked = $locked;
        return $this;
    }

    /**
     * Get locked
     *
     * @return boolean $locked
     */
    public function getLocked() {
        return $this->locked;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
     * @return User
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean $enabled
     */
    public function getEnabled() {
        return $this->enabled;
    }

    /**
     * Set salt
     *
     * @param string $salt
     * @return User
     */
    public function setSalt($salt) {
        $this->salt = $salt;
        return $this;
    }

    /**
     * Set Roles
     *
     * @param array $roles
     * @return User
     */
    public function setRoles($roles) {
        $this->roles = $roles;
        return $this;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email) {
        $this->email = $email;
        return $this;
    }

    /**
     * Get email
     *
     * @return string $email
     */
    public function getEmail() {
        return $this->email;
    }

}
