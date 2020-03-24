<?php

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * User
 * @ORM\Entity @ORM\Table(name="users")
 */
class User
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="login", type="string")
     */
    protected $login;

    /**
     * @ORM\Column(name="pass", type="string")
     */
    protected $pass;

    /**
     * @ORM\Column(name="email", type="string")
     */
    protected $email;

}