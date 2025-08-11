<?php
/**
 * Amazon Product Advertising API Endpoints Configuration
 *
 * This file contains all API endpoint configurations, supported operations,
 * and regional settings for the Amazon Product Advertising API.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/config
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Product Advertising API Configuration
 */
return array(

    /**
     * API Version and Service Information
     */
    'api_version' => '2020-10-01',
    'service_name' => 'ProductAdvertisingAPI',
    'content_type' => 'application/json; charset=utf-8',
    'user_agent' => 'AmazonProductImporter/1.0.0 (Language=PHP)',

    /**
     * Regional Endpoints Configuration
     * Each region has its own endpoint URL and supported marketplaces
     */
    'regions' => array(
        'us-east-1' => array(
            'name' => 'US East (N. Virginia)',
            'endpoint' => 'https://webservices.amazon.com/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.com',
            'marketplaces' => array('www.amazon.com', 'www.amazon.ca', 'www.amazon.com.mx'),
            'currency' => 'USD',
            'language' => 'en_US'
        ),
        'us-west-2' => array(
            'name' => 'US West (Oregon)',
            'endpoint' => 'https://webservices.amazon.com/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.com',
            'marketplaces' => array('www.amazon.com'),
            'currency' => 'USD',
            'language' => 'en_US'
        ),
        'eu-west-1' => array(
            'name' => 'Europe (Ireland)',
            'endpoint' => 'https://webservices.amazon.co.uk/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.co.uk',
            'marketplaces' => array(
                'www.amazon.co.uk',
                'www.amazon.de',
                'www.amazon.fr',
                'www.amazon.it',
                'www.amazon.es',
                'www.amazon.nl',
                'www.amazon.se',
                'www.amazon.pl',
                'www.amazon.com.tr'
            ),
            'currency' => 'EUR',
            'language' => 'en_GB'
        ),
        'ap-northeast-1' => array(
            'name' => 'Asia Pacific (Tokyo)',
            'endpoint' => 'https://webservices.amazon.co.jp/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.co.jp',
            'marketplaces' => array('www.amazon.co.jp'),
            'currency' => 'JPY',
            'language' => 'ja_JP'
        ),
        'ap-southeast-1' => array(
            'name' => 'Asia Pacific (Singapore)',
            'endpoint' => 'https://webservices.amazon.sg/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.sg',
            'marketplaces' => array('www.amazon.sg'),
            'currency' => 'SGD',
            'language' => 'en_SG'
        ),
        'ap-southeast-2' => array(
            'name' => 'Asia Pacific (Sydney)',
            'endpoint' => 'https://webservices.amazon.com.au/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.com.au',
            'marketplaces' => array('www.amazon.com.au'),
            'currency' => 'AUD',
            'language' => 'en_AU'
        ),
        'ap-south-1' => array(
            'name' => 'Asia Pacific (Mumbai)',
            'endpoint' => 'https://webservices.amazon.in/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.in',
            'marketplaces' => array('www.amazon.in'),
            'currency' => 'INR',
            'language' => 'en_IN'
        ),
        'sa-east-1' => array(
            'name' => 'South America (São Paulo)',
            'endpoint' => 'https://webservices.amazon.com.br/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.com.br',
            'marketplaces' => array('www.amazon.com.br'),
            'currency' => 'BRL',
            'language' => 'pt_BR'
        ),
        'me-south-1' => array(
            'name' => 'Middle East (Bahrain)',
            'endpoint' => 'https://webservices.amazon.ae/paapi5/searchitems',
            'base_url' => 'https://webservices.amazon.ae',
            'marketplaces' => array('www.amazon.ae', 'www.amazon.sa'),
            'currency' => 'AED',
            'language' => 'en_AE'
        )
    ),

    /**
     * API Operations Configuration
     * Each operation has its specific endpoint path and supported parameters
     */
    'operations' => array(
        'SearchItems' => array(
            'path' => '/paapi5/searchitems',
            'method' => 'POST',
            'description' => 'Search for items on Amazon',
            'required_params' => array('Keywords', 'PartnerTag', 'PartnerType'),
            'optional_params' => array(
                'Actor', 'Artist', 'Author', 'Availability', 'Brand', 'BrowseNodeId',
                'Condition', 'CurrencyOfPreference', 'DeliveryFlags', 'ItemCount',
                'ItemPage', 'Keywords', 'LanguagesOfPreference', 'MaxPrice', 'Merchant',
                'MinPrice', 'MinReviewsRating', 'MinSavingPercent', 'OfferCount',
                'Properties', 'SearchIndex', 'SortBy', 'Title'
            ),
            'max_item_count' => 10,
            'max_item_page' => 10
        ),
        'GetItems' => array(
            'path' => '/paapi5/getitems',
            'method' => 'POST',
            'description' => 'Get detailed information for specific items',
            'required_params' => array('ItemIds', 'PartnerTag', 'PartnerType'),
            'optional_params' => array(
                'Condition', 'CurrencyOfPreference', 'LanguagesOfPreference',
                'Merchant', 'OfferCount', 'Resources'
            ),
            'max_items' => 10
        ),
        'GetVariations' => array(
            'path' => '/paapi5/getvariations',
            'method' => 'POST',
            'description' => 'Get variation information for a parent ASIN',
            'required_params' => array('ASIN', 'PartnerTag', 'PartnerType'),
            'optional_params' => array(
                'Condition', 'CurrencyOfPreference', 'LanguagesOfPreference',
                'Merchant', 'OfferCount', 'Resources', 'VariationCount', 'VariationPage'
            ),
            'max_variation_count' => 10,
            'max_variation_page' => 10
        ),
        'GetBrowseNodes' => array(
            'path' => '/paapi5/getbrowsenodes',
            'method' => 'POST',
            'description' => 'Get browse node information',
            'required_params' => array('BrowseNodeIds', 'PartnerTag', 'PartnerType'),
            'optional_params' => array('LanguagesOfPreference', 'Resources'),
            'max_browse_nodes' => 10
        )
    ),

    /**
     * Marketplace Configuration
     * Detailed information about each supported marketplace
     */
    'marketplaces' => array(
        'www.amazon.com' => array(
            'name' => 'Amazon.com (US)',
            'country_code' => 'US',
            'currency' => 'USD',
            'language' => 'en_US',
            'region' => 'us-east-1',
            'locale' => 'en-US',
            'domain' => 'amazon.com',
            'timezone' => 'America/New_York'
        ),
        'www.amazon.ca' => array(
            'name' => 'Amazon.ca (Canada)',
            'country_code' => 'CA',
            'currency' => 'CAD',
            'language' => 'en_CA',
            'region' => 'us-east-1',
            'locale' => 'en-CA',
            'domain' => 'amazon.ca',
            'timezone' => 'America/Toronto'
        ),
        'www.amazon.com.mx' => array(
            'name' => 'Amazon.com.mx (Mexico)',
            'country_code' => 'MX',
            'currency' => 'MXN',
            'language' => 'es_MX',
            'region' => 'us-east-1',
            'locale' => 'es-MX',
            'domain' => 'amazon.com.mx',
            'timezone' => 'America/Mexico_City'
        ),
        'www.amazon.co.uk' => array(
            'name' => 'Amazon.co.uk (United Kingdom)',
            'country_code' => 'GB',
            'currency' => 'GBP',
            'language' => 'en_GB',
            'region' => 'eu-west-1',
            'locale' => 'en-GB',
            'domain' => 'amazon.co.uk',
            'timezone' => 'Europe/London'
        ),
        'www.amazon.de' => array(
            'name' => 'Amazon.de (Germany)',
            'country_code' => 'DE',
            'currency' => 'EUR',
            'language' => 'de_DE',
            'region' => 'eu-west-1',
            'locale' => 'de-DE',
            'domain' => 'amazon.de',
            'timezone' => 'Europe/Berlin'
        ),
        'www.amazon.fr' => array(
            'name' => 'Amazon.fr (France)',
            'country_code' => 'FR',
            'currency' => 'EUR',
            'language' => 'fr_FR',
            'region' => 'eu-west-1',
            'locale' => 'fr-FR',
            'domain' => 'amazon.fr',
            'timezone' => 'Europe/Paris'
        ),
        'www.amazon.it' => array(
            'name' => 'Amazon.it (Italy)',
            'country_code' => 'IT',
            'currency' => 'EUR',
            'language' => 'it_IT',
            'region' => 'eu-west-1',
            'locale' => 'it-IT',
            'domain' => 'amazon.it',
            'timezone' => 'Europe/Rome'
        ),
        'www.amazon.es' => array(
            'name' => 'Amazon.es (Spain)',
            'country_code' => 'ES',
            'currency' => 'EUR',
            'language' => 'es_ES',
            'region' => 'eu-west-1',
            'locale' => 'es-ES',
            'domain' => 'amazon.es',
            'timezone' => 'Europe/Madrid'
        ),
        'www.amazon.nl' => array(
            'name' => 'Amazon.nl (Netherlands)',
            'country_code' => 'NL',
            'currency' => 'EUR',
            'language' => 'nl_NL',
            'region' => 'eu-west-1',
            'locale' => 'nl-NL',
            'domain' => 'amazon.nl',
            'timezone' => 'Europe/Amsterdam'
        ),
        'www.amazon.co.jp' => array(
            'name' => 'Amazon.co.jp (Japan)',
            'country_code' => 'JP',
            'currency' => 'JPY',
            'language' => 'ja_JP',
            'region' => 'ap-northeast-1',
            'locale' => 'ja-JP',
            'domain' => 'amazon.co.jp',
            'timezone' => 'Asia/Tokyo'
        ),
        'www.amazon.com.au' => array(
            'name' => 'Amazon.com.au (Australia)',
            'country_code' => 'AU',
            'currency' => 'AUD',
            'language' => 'en_AU',
            'region' => 'ap-southeast-2',
            'locale' => 'en-AU',
            'domain' => 'amazon.com.au',
            'timezone' => 'Australia/Sydney'
        ),
        'www.amazon.sg' => array(
            'name' => 'Amazon.sg (Singapore)',
            'country_code' => 'SG',
            'currency' => 'SGD',
            'language' => 'en_SG',
            'region' => 'ap-southeast-1',
            'locale' => 'en-SG',
            'domain' => 'amazon.sg',
            'timezone' => 'Asia/Singapore'
        ),
        'www.amazon.in' => array(
            'name' => 'Amazon.in (India)',
            'country_code' => 'IN',
            'currency' => 'INR',
            'language' => 'en_IN',
            'region' => 'ap-south-1',
            'locale' => 'en-IN',
            'domain' => 'amazon.in',
            'timezone' => 'Asia/Kolkata'
        ),
        'www.amazon.com.br' => array(
            'name' => 'Amazon.com.br (Brazil)',
            'country_code' => 'BR',
            'currency' => 'BRL',
            'language' => 'pt_BR',
            'region' => 'sa-east-1',
            'locale' => 'pt-BR',
            'domain' => 'amazon.com.br',
            'timezone' => 'America/Sao_Paulo'
        ),
        'www.amazon.ae' => array(
            'name' => 'Amazon.ae (UAE)',
            'country_code' => 'AE',
            'currency' => 'AED',
            'language' => 'en_AE',
            'region' => 'me-south-1',
            'locale' => 'en-AE',
            'domain' => 'amazon.ae',
            'timezone' => 'Asia/Dubai'
        ),
        'www.amazon.sa' => array(
            'name' => 'Amazon.sa (Saudi Arabia)',
            'country_code' => 'SA',
            'currency' => 'SAR',
            'language' => 'ar_SA',
            'region' => 'me-south-1',
            'locale' => 'ar-SA',
            'domain' => 'amazon.sa',
            'timezone' => 'Asia/Riyadh'
        )
    ),

    /**
     * Search Index Configuration
     * Available search categories for each marketplace
     */
    'search_indices' => array(
        'global' => array(
            'All' => 'All Departments',
            'AmazonVideo' => 'Amazon Video',
            'Apparel' => 'Clothing, Shoes & Jewelry',
            'Appliances' => 'Appliances',
            'ArtsAndCrafts' => 'Arts, Crafts & Sewing',
            'Automotive' => 'Automotive',
            'Baby' => 'Baby',
            'Beauty' => 'Beauty & Personal Care',
            'Books' => 'Books',
            'Classical' => 'Classical Music',
            'Computers' => 'Computers',
            'DigitalMusic' => 'Digital Music',
            'Electronics' => 'Electronics',
            'EverythingElse' => 'Everything Else',
            'Fashion' => 'Fashion',
            'GardenAndOutdoor' => 'Garden & Outdoor',
            'GiftCards' => 'Gift Cards',
            'GroceryAndGourmetFood' => 'Grocery & Gourmet Food',
            'Handmade' => 'Handmade',
            'HealthPersonalCare' => 'Health & Personal Care',
            'HomeAndKitchen' => 'Home & Kitchen',
            'Industrial' => 'Industrial & Scientific',
            'Jewelry' => 'Jewelry',
            'KindleStore' => 'Kindle Store',
            'Luggage' => 'Luggage & Travel Gear',
            'LuxuryBeauty' => 'Luxury Beauty',
            'Magazines' => 'Magazine Subscriptions',
            'Movies' => 'Movies & TV',
            'Music' => 'Music',
            'MusicalInstruments' => 'Musical Instruments',
            'OfficeProducts' => 'Office Products',
            'PetSupplies' => 'Pet Supplies',
            'Software' => 'Software',
            'SportsAndOutdoors' => 'Sports & Outdoors',
            'ToolsAndHomeImprovement' => 'Tools & Home Improvement',
            'ToysAndGames' => 'Toys & Games',
            'VHS' => 'VHS',
            'VideoGames' => 'Video Games',
            'Watches' => 'Watches'
        ),
        'us_specific' => array(
            'AlexaSkills' => 'Alexa Skills',
            'AmazonFresh' => 'Amazon Fresh',
            'Collectibles' => 'Collectibles & Fine Art',
            'Entertainment' => 'Entertainment Collectibles',
            'MobileApps' => 'Apps & Games'
        ),
        'jp_specific' => array(
            'AmazonPantry' => 'Amazon Pantry',
            'CreditCards' => 'Credit Cards',
            'Food' => 'Food & Beverage',
            'Hobbies' => 'Hobbies'
        )
    ),

    /**
     * API Resource Types
     * Available data that can be requested from the API
     */
    'resources' => array(
        'item_info' => array(
            'ItemInfo.ByLineInfo',
            'ItemInfo.ContentInfo',
            'ItemInfo.ContentRating',
            'ItemInfo.Classifications',
            'ItemInfo.ExternalIds',
            'ItemInfo.Features',
            'ItemInfo.ManufactureInfo',
            'ItemInfo.ProductInfo',
            'ItemInfo.TechnicalInfo',
            'ItemInfo.Title',
            'ItemInfo.TradeInInfo'
        ),
        'offers' => array(
            'Offers.Listings.Availability.MaxOrderQuantity',
            'Offers.Listings.Availability.Message',
            'Offers.Listings.Availability.MinOrderQuantity',
            'Offers.Listings.Availability.Type',
            'Offers.Listings.Condition',
            'Offers.Listings.Condition.ConditionNote',
            'Offers.Listings.Condition.SubCondition',
            'Offers.Listings.DeliveryInfo.IsAmazonFulfilled',
            'Offers.Listings.DeliveryInfo.IsFreeShippingEligible',
            'Offers.Listings.DeliveryInfo.IsPrimeEligible',
            'Offers.Listings.DeliveryInfo.ShippingCharges',
            'Offers.Listings.IsBuyBoxWinner',
            'Offers.Listings.LoyaltyPoints.Points',
            'Offers.Listings.MerchantInfo',
            'Offers.Listings.Price',
            'Offers.Listings.ProgramEligibility.IsPrimeExclusive',
            'Offers.Listings.ProgramEligibility.IsPrimePantry',
            'Offers.Listings.Promotions',
            'Offers.Listings.SavingBasis',
            'Offers.Summaries.HighestPrice',
            'Offers.Summaries.LowestPrice',
            'Offers.Summaries.OfferCount'
        ),
        'images' => array(
            'Images.Primary.Small',
            'Images.Primary.Medium',
            'Images.Primary.Large',
            'Images.Variants.Small',
            'Images.Variants.Medium',
            'Images.Variants.Large'
        ),
        'browse_node_info' => array(
            'BrowseNodeInfo.BrowseNodes',
            'BrowseNodeInfo.BrowseNodes.Ancestor',
            'BrowseNodeInfo.BrowseNodes.Children',
            'BrowseNodeInfo.WebsiteSalesRank'
        ),
        'customer_reviews' => array(
            'CustomerReviews.Count',
            'CustomerReviews.StarRating'
        ),
        'search_refinements' => array(
            'SearchRefinements'
        )
    ),

    /**
     * Rate Limiting Configuration
     */
    'rate_limits' => array(
        'requests_per_second' => 1,
        'requests_per_day' => 8640,
        'burst_requests' => 10,
        'throttle_delay' => 1000, // milliseconds
        'backoff_multiplier' => 2,
        'max_retries' => 3,
        'retry_delay' => 1000 // milliseconds
    ),

    /**
     * Request Configuration
     */
    'request_config' => array(
        'timeout' => 30, // seconds
        'connect_timeout' => 10, // seconds
        'user_agent' => 'AmazonProductImporter/1.0.0 (WordPress)',
        'max_redirects' => 3,
        'ssl_verify' => true,
        'compression' => true,
        'keep_alive' => true
    ),

    /**
     * Error Codes and Messages
     */
    'error_codes' => array(
        'InvalidParameterValue' => 'One or more parameter values are invalid',
        'InvalidParameterCombination' => 'Invalid parameter combination',
        'MissingParameter' => 'Required parameter is missing',
        'RequestThrottled' => 'Request was throttled due to rate limiting',
        'InvalidAssociate' => 'Invalid Associate Tag',
        'AccessDenied' => 'Access denied - check credentials',
        'ItemsNotFound' => 'No items found for the given parameters',
        'TooManyRequests' => 'Too many requests - rate limit exceeded',
        'InternalError' => 'Internal server error',
        'ServiceUnavailable' => 'Service temporarily unavailable',
        'InvalidSignature' => 'Invalid request signature',
        'SignatureDoesNotMatch' => 'Request signature does not match',
        'RequestExpired' => 'Request timestamp expired',
        'InvalidTimestamp' => 'Invalid timestamp format'
    ),

    /**
     * Default Parameters
     */
    'default_params' => array(
        'PartnerType' => 'Associates',
        'ItemCount' => 10,
        'ItemPage' => 1,
        'SearchIndex' => 'All',
        'SortBy' => 'Relevance',
        'Condition' => 'New',
        'OfferCount' => 1,
        'Resources' => array(
            'ItemInfo.Title',
            'ItemInfo.Features',
            'ItemInfo.ProductInfo',
            'ItemInfo.ManufactureInfo',
            'ItemInfo.ByLineInfo',
            'Images.Primary.Large',
            'Images.Variants.Large',
            'Offers.Listings.Price',
            'Offers.Listings.Availability',
            'Offers.Listings.Condition',
            'Offers.Listings.DeliveryInfo',
            'CustomerReviews.StarRating',
            'CustomerReviews.Count',
            'BrowseNodeInfo.BrowseNodes'
        )
    ),

    /**
     * Sort Options
     */
    'sort_options' => array(
        'Relevance' => 'Most Relevant',
        'Price:LowToHigh' => 'Price: Low to High',
        'Price:HighToLow' => 'Price: High to Low',
        'NewestArrivals' => 'Newest Arrivals',
        'AvgCustomerReviews' => 'Customer Reviews',
        'Featured' => 'Featured'
    ),

    /**
     * Condition Types
     */
    'condition_types' => array(
        'Any' => 'Any Condition',
        'New' => 'New',
        'Used' => 'Used',
        'Collectible' => 'Collectible',
        'Refurbished' => 'Refurbished'
    ),

    /**
     * Availability Types
     */
    'availability_types' => array(
        'Available' => 'Available',
        'IncludeOutOfStock' => 'Include Out of Stock'
    ),

    /**
     * Currency Symbols
     */
    'currency_symbols' => array(
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'INR' => '₹',
        'BRL' => 'R$',
        'MXN' => '$',
        'SGD' => 'S$',
        'AED' => 'د.إ',
        'SAR' => 'ر.س'
    ),

    /**
     * Image Size Variants
     */
    'image_sizes' => array(
        'SL75' => array('width' => 75, 'height' => 75),
        'SL110' => array('width' => 110, 'height' => 110),
        'SL160' => array('width' => 160, 'height' => 160),
        'SL200' => array('width' => 200, 'height' => 200),
        'SL300' => array('width' => 300, 'height' => 300),
        'SL500' => array('width' => 500, 'height' => 500),
        'SL1000' => array('width' => 1000, 'height' => 1000),
        'SL1500' => array('width' => 1500, 'height' => 1500)
    ),

    /**
     * Cache Configuration
     */
    'cache_config' => array(
        'search_results_ttl' => 3600, // 1 hour
        'product_details_ttl' => 7200, // 2 hours
        'browse_nodes_ttl' => 86400, // 24 hours
        'variation_data_ttl' => 3600, // 1 hour
        'api_status_ttl' => 300, // 5 minutes
        'rate_limit_ttl' => 3600 // 1 hour
    ),

    /**
     * Validation Rules
     */
    'validation' => array(
        'asin_pattern' => '/^[A-Z0-9]{10}$/',
        'max_keywords_length' => 255,
        'min_keywords_length' => 2,
        'max_items_per_request' => 10,
        'max_variations_per_request' => 10,
        'max_browse_nodes_per_request' => 10
    ),

    /**
     * Testing Configuration
     */
    'testing' => array(
        'test_asins' => array(
            'B08N5WRWNW', // Example ASIN for testing
            'B00X4WHP5E',
            'B01DFKC2SO'
        ),
        'test_keywords' => array(
            'wireless headphones',
            'laptop',
            'coffee maker'
        ),
        'sandbox_mode' => false,
        'debug_requests' => false
    )
);