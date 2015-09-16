<?php
/**
 * 2013-2015 Nosto Solutions Ltd
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@nosto.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2013-2015 Nosto Solutions Ltd
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Buyer info model used bu the order model.
 */
class NostoTaggingOrderBuyer implements NostoOrderBuyerInterface
{
	/**
	 * @var string the first name of the one who placed the order.
	 */
	protected $first_name;

	/**
	 * @var string the last name of the one who placed the order.
	 */
	protected $last_name;

	/**
	 * @var string the email address of the one who placed the order.
	 */
	protected $email;

	/**
	 * Constructor.
	 *
	 * Sets the value objects data from the PS customer model.
	 *
	 * @param Customer|CustomerCore $customer the PS customer model.
	 */
	public function __construct(Customer $customer)
	{
		$this->first_name = $customer->firstname;
		$this->last_name = $customer->lastname;
		$this->email = $customer->email;
	}

	/**
	 * Gets the first name of the user who placed the order.
	 *
	 * @return string the first name.
	 */
	public function getFirstName()
	{
		return $this->first_name;
	}

	/**
	 * Gets the last name of the user who placed the order.
	 *
	 * @return string the last name.
	 */
	public function getLastName()
	{
		return $this->last_name;
	}

	/**
	 * Gets the email address of the user who placed the order.
	 *
	 * @return string the email address.
	 */
	public function getEmail()
	{
		return $this->email;
	}
}
