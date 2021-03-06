<?php
/**
 * 2013-2016 Nosto Solutions Ltd
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
 * @copyright 2013-2016 Nosto Solutions Ltd
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Model for tagging products.
 */
class NostoTaggingProduct extends NostoTaggingModel implements NostoProductInterface, NostoValidatableInterface
{

    /**
     * @var string absolute url to the product page.
     */
    protected $url;

    /**
     * @var string product object id.
     */
    protected $product_id;

    /**
     * @var string product name.
     */
    protected $name;

    /**
     * @var string absolute url to the product image.
     */
    protected $image_url;

    /**
     * @var string product price, discounted including vat.
     */
    protected $price;

    /**
     * @var string product list price, including vat.
     */
    protected $list_price;

    /**
     * @var string the currency iso code.
     */
    protected $currency_code;

    /**
     * @var string product availability (use constants).
     */
    protected $availability;

    /**
     * @var string variationId
     */
    protected $variationId;

    /**
     * @var array list of product tags.
     */
    protected $tags = array(
        'tag1' => array(),
        'tag2' => array(),
        'tag3' => array(),
    );
    /**
     * @var array list of product category strings.
     */
    protected $categories = array();

    /**
     * @var string the product short description.
     */
    protected $short_description;

    /**
     * @var string the product description.
     */
    protected $description;

    /**
     * @var string the product brand name.
     */
    protected $brand;

    /**
     * @var string the product publish date.
     */
    protected $date_published;

    /**
     * @inheritdoc
     */
    public function getValidationRules()
    {
        return array();
    }

    /**
     * Loads the product data from supplied context and product objects.
     *
     * @param Context $context the context object.
     * @param Product $product the product object.
     */
    public function loadData(Context $context, Product $product)
    {
        if (!Validate::isLoadedObject($product)) {
            return;
        }

        /** @var NostoTaggingHelperUrl $url_helper */
        $url_helper = Nosto::helper('nosto_tagging/url');
        /** @var NostoTaggingHelperPrice $helper_price */
        $helper_price = Nosto::helper('nosto_tagging/price');
        /** @var NostoTaggingHelperCurrency $helper_currency */
        $helper_currency = Nosto::helper('nosto_tagging/currency');
        /** @var NostoTaggingHelperConfig $helper_config */
        $helper_config = Nosto::helper('nosto_tagging/config');
        /** @var NostoHelperPrice $helper_config */
        $nosto_helper_price = Nosto::helper('nosto/price');

        $base_currency = $helper_currency->getBaseCurrency($context);

        $id_lang = $context->language->id;
        $id_shop = $context->shop->id;

        if ($helper_config->useMultipleCurrencies($id_lang) === true) {
            $this->variationId = $base_currency->iso_code;
            $tagging_currency = $base_currency;
        } else {
            $this->variationId = false;
            $tagging_currency= $context->currency;
        }

        $this->url = $url_helper->getProductUrl($product, $id_lang, $id_shop);
        $this->image_url = $url_helper->getProductImageUrl($product);
        $this->product_id = (int)$product->id;
        $this->name = $product->name;

        $this->price = $nosto_helper_price->format(
            $helper_price->getProductPriceInclTax(
                $product,
                $context,
                $tagging_currency
            )
        );
        $this->list_price = $nosto_helper_price->format(
            $helper_price->getProductListPriceInclTax(
                $product,
                $context,
                $tagging_currency
            )
        );
        $this->currency_code = Tools::strtoupper($tagging_currency->iso_code);

        $this->availability = $this->checkAvailability($product);
        $this->tags['tag1'] = $this->buildTags($product, $id_lang);
        $this->categories = $this->buildCategories($product, $id_lang);
        $this->short_description = $product->description_short;
        $this->description = $product->description;
        $this->brand = (!empty($product->manufacturer_name)) ? $product->manufacturer_name : null;
        $this->date_published = Nosto::helper('date')->format($product->date_add);

        $this->dispatchHookActionLoadAfter(array(
            'nosto_product' => $this,
            'product' => $product,
            'context' => $context
        ));
    }

    /**
     * Assigns the product ID from given product.
     *
     * This method exists in order to expose a public API to change the ID.
     *
     * @param Product $product the product object.
     */
    public function assignId(Product $product)
    {
        $this->product_id = (int)$product->id;
    }

    /**
     * Checks the availability of the product and returns the "availability constant".
     *
     * The product is considered available if it is visible in the shop and is in stock.
     *
     * @param Product $product the product model.
     * @return string the value, i.e. self::IN_STOCK or self::OUT_OF_STOCK.
     */
    protected function checkAvailability(Product $product)
    {
        if (_PS_VERSION_ >= '1.5' && $product->visibility === 'none') {
            return self::INVISIBLE;
        } else {
            return ($product->checkQty(1)) ? self::IN_STOCK : self::OUT_OF_STOCK;
        }
    }

    /**
     * Builds the tag list for the product.
     *
     * Also includes the custom "add-to-cart" tag if the product can be added to the shopping cart directly without
     * any action from the user, e.g. the product cannot have any variations or choices. This tag is then used in the
     * recommendations to render the "Add to cart" button for the product when it is recommended to a user.
     *
     * @param Product $product the product model.
     * @param int $id_lang for which language ID to fetch the product tags.
     * @return array the built tags.
     */
    protected function buildTags(Product $product, $id_lang)
    {
        $tags = array();
        if (($product_tags = $product->getTags($id_lang)) !== '') {
            $tags = explode(', ', $product_tags);
        }

        // If the product has no attributes (color, size etc.), then we mark it as possible to add directly to cart.
        $product_attributes = $product->getAttributesGroups($id_lang);
        if (empty($product_attributes)) {
            $tags[] = self::ADD_TO_CART;
        }

        return $tags;
    }

    /**
     * Builds the category paths the product belongs to and returns them.
     *
     * By "path" we mean the full tree path of the products categories and sub-categories.
     *
     * @param Product $product the product model.
     * @param int $id_lang for which language ID to fetch the categories.
     * @return array the built category paths.
     */
    protected function buildCategories(Product $product, $id_lang)
    {
        $categories = array();
        foreach ($product->getCategories() as $category_id) {
            $category = NostoTaggingCategory::buildCategoryString($category_id, $id_lang);
            if (!empty($category)) {
                $categories[] = $category;
            }
        }
        return $categories;
    }

    /**
     * Returns the absolute url to the product page in the shop frontend.
     *
     * @return string the url.
     */
    public function getUrl()
    {
        return $this->url;
    }
    
    /**
     * Returns the product's unique identifier.
     *
     * @return int|string the ID.
     */
    public function getProductId()
    {
        return $this->product_id;
    }
    
    /**
     * Returns the name of the product.
     *
     * @return string the name.
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Returns the absolute url the one of the product images in the shop frontend.
     *
     * @return string the url.
     */
    public function getImageUrl()
    {
        return $this->image_url;
    }
    
    /**
     * Returns the absolute url to one of the product image thumbnails in the shop frontend.
     *
     * @return string the url.
     */
    public function getThumbUrl()
    {
        return null;
    }
    
    /**
     * Returns the price of the product including possible discounts and taxes.
     *
     * @return string the price.
     */
    public function getPrice()
    {
        return $this->price;
    }
    
    /**
     * Returns the list price of the product without discounts but including possible taxes.
     *
     * @return string the price.
     */
    public function getListPrice()
    {
        return $this->list_price;
    }
    
    /**
     * Returns the currency code (ISO 4217) the product is sold in.
     *
     * @return string the currency code.
     */
    public function getCurrencyCode()
    {
        return $this->currency_code;
    }

    /**
     * Returns the availability of the product, i.e. if it is in stock or not.
     *
     * @return string the availability.
     */
    public function getAvailability()
    {
        return $this->availability;
    }
    
    /**
     * Returns the tags for the product.
     *
     * @return array the tags array, e.g. array('tag1' => array("winter", "shoe")).
     */
    public function getTags()
    {
        return $this->tags;
    }
    
    /**
     * Returns the categories the product is located in.
     *
     * @return array list of category strings, e.g. array("/shoes/winter", "shoes/boots").
     */
    public function getCategories()
    {
        return $this->categories;
    }
    
    /**
     * Returns the product short description.
     *
     * @return string the short description.
     */
    public function getShortDescription()
    {
        return $this->short_description;
    }
    
    /**
     * Returns the product description.
     *
     * @return string the description.
     */
    public function getDescription()
    {
        return $this->description;
    }
    
    /**
     * Returns the full product description,
     * i.e. both the "short" and "normal" descriptions concatenated.
     *
     * @return string the full descriptions.
     */
    public function getFullDescription()
    {
        $descriptions = array();
        if (!empty($this->short_description)) {
            $descriptions[] = $this->short_description;
        }
        if (!empty($this->description)) {
            $descriptions[] = $this->description;
        }
        return implode(' ', $descriptions);
    }
    
    /**
     * Returns the product brand name.
     *
     * @return string the brand name.
     */
    public function getBrand()
    {
        return $this->brand;
    }
    
    /**
     * Returns the product publication date in the shop.
     *
     * @return string the date.
     */
    public function getDatePublished()
    {
        return $this->date_published;
    }
    
    /**
     * Sets the product ID from given product.
     *
     * @param int $id the product ID.
     */
    public function setProductId($id)
    {
        $this->product_id = $id;
    }
    
    /**
     * Sets the availability state of the product.
     *
     * @param string $availability the availability.
     */
    public function setAvailability($availability)
    {
        $this->availability = $availability;
    }
    
    /**
     * Sets the currency code (ISO 4217) the product is sold in.
     *
     * @param string $currency the currency code.
     */
    public function setCurrencyCode($currency)
    {
        $this->currency_code = $currency;
    }
    
    /**
     * Sets the products published date.
     *
     * @param string $date the date.
     */
    public function setDatePublished($date)
    {
        $this->date_published = $date;
    }
    
    /**
     * Sets the product price.
     *
     * @param int $price the price.
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * Sets the product list price.
     *
     * @param int $list_price the price.
     */
    public function setListPrice($list_price)
    {
        $this->list_price = $list_price;
    }

    /**
     * Sets the tags array for the `tag1` attribute.
     *
     * The tags must be an array of non-empty values.
     *
     * Usage:
     * $object->setTag1(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     */
    public function setTag1(array $tags)
    {
        $this->tags['tag1'] = array();
        foreach ($tags as $tag) {
            $this->addTag1($tag);
        }
    }
    
    /**
     * Adds a new tag to the `tag1` field.
     *
     * The tag must be a non-empty value.
     *
     * Usage:
     * $object->addTag1('customTag');
     *
     * @param string $tag the tag to add.
     */
    public function addTag1($tag)
    {
        $this->tags['tag1'][] = $tag;
    }
    
    /**
     * Sets the tags array for the `tag2` attribute.
     *
     * The tags must be an array of non-empty values.
     *
     * Usage:
     * $object->setTag2(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     */
    public function setTag2(array $tags)
    {
        $this->tags['tag2'] = array();
        foreach ($tags as $tag) {
            $this->addTag2($tag);
        }
    }
    
    /**
     * Adds a new tag to the `tag2` field.
     *
     * The tag must be a non-empty  value.
     *
     * Usage:
     * $object->addTag2('customTag');
     *
     * @param string $tag the tag to add.
     */
    public function addTag2($tag)
    {
        $this->tags['tag2'][] = $tag;
    }
    
    /**
     * Sets the tags array for the `tag3` attribute.
     *
     * The tags must be an array of non-empty values.
     *
     * Usage:
     * $object->setTag3(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     */
    public function setTag3(array $tags)
    {
        $this->tags['tag3'] = array();
        foreach ($tags as $tag) {
            $this->addTag3($tag);
        }
    }
    
    /**
     * Adds a new tag to the `tag3` field.
     *
     * The tag must be a non-empty value.
     *
     * Usage:
     * $object->addTag3('customTag');
     *
     * @param string $tag the tag to add.
     */
    public function addTag3($tag)
    {
        $this->tags['tag3'][] = $tag;
    }
    /**
     * Sets the brand name of the product manufacturer.
     *
     * The name must be a non-empty string.
     *
     * @param string $brand the brand name.
     */
    public function setBrand($brand)
    {
        $this->brand = $brand;
    }
    /**
     * Sets the product categories.
     *
     * The categories must be an array of non-empty values. The categories are expected to include the entire
     * sub/parent category path, e.g. "clothes/winter/coats".
     *
     * Usage:
     * $object->setCategories(array('clothes/winter/coats' [, ... ] ));
     *
     * @param array $categories the categories.
     */
    public function setCategories(array $categories)
    {
        $this->categories = array();
        foreach ($categories as $category) {
            $this->addCategory($category);
        }
    }

    /**
     * Adds a category to the product.
     *
     * The category must be a non-empty and is expected to include the entire sub/parent category path,
     * e.g. "clothes/winter/coats".
     *
     * Usage:
     * $object->addCategory('clothes/winter/coats');
     *
     * @param string $category the category.
     */
    public function addCategory($category)
    {
        $this->categories[] = $category;
    }

    /**
     * Sets the product name.
     *
     * The name must be a non-empty value.
     *
     * Usage:
     * $object->setName('Example');
     *
     * @param string $name the name.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Sets the URL for the product page in the shop frontend that shows this product.
     *
     * The URL must be absolute, i.e. must include the protocol http or https.
     *
     * Usage:
     * $object->setUrl("http://my.shop.com/products/example.html");
     *
     * @param string $url the url.
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Sets the image URL for the product.
     *
     * The URL must be absolute, i.e. must include the protocol http or https.
     *
     * Usage:
     * $object->setImageUrl("http://my.shop.com/media/example.jpg");
     *
     * @param string $image_url the url.
     */
    public function setImageUrl($image_url)
    {
        $this->image_url = $image_url;
    }

    /**
     * Sets the product description.
     *
     * The description must be a non-empty string.
     *
     * @param string $description the description.
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }
    /**
     * Sets the product `short` description.
     *
     * The description must be a non-empty string.
     *
     * Usage:
     * $object->setShortDescription('Lorem ipsum dolor sit amet, ludus possim ut ius.');
     *
     * @param string $short_description the `short` description.
     */
    public function setShortDescription($short_description)
    {
        $this->short_description = $short_description;
    }

    /**
     * Sets the variation id
     *
     * @param string $variationId
     */
    public function setVariationId($variationId)
    {
        $this->variationId = $variationId;
    }

    /*
     * @inheritdoc
     */
    public function getVariationId()
    {
        return $this->variationId;
    }
}
