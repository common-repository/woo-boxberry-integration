<?php
/**
 *
 *  * This file is part of Boxberry Api.
 *  *
 *  * (c) 2016, T. I. R. Ltd.
 *  * Evgeniy Mosunov, Alexander Borovikov
 *  *
 *  * For the full copyright and license information, please view LICENSE
 *  * file that was distributed with this source code
 *  *
 *  * File: ParselListRequest.php
 *  * Created: 26.07.2016
 *  *
 */

namespace Boxberry\Requests;

/**
 * Class ParselListRequest
 * @package Boxberry\Requests
 */
class ParselListRequest extends Request
{
    /**
     * @var string
     */
    protected $resultClass = 'Boxberry\\Collections\\ImgIdsCollection';
    /**
     * @var array
     */
    protected $propNameMap = array(
        'ImId' => 'imId'
    );
    /**
     * @var string
     */
    protected $imId = null;

    /**
     * @return string
     */
    public function getImId()
    {
        return $this->imId;
    }

    /**
     * @param string $imId
     */
    public function setImId($imId)
    {
        $this->imId = $imId;
    }

    /**
     * @return bool
     */
    function checkRequiredFields()
    {
        if ($this->getImId() === null) {
            return false;
        }
        return true;
    }
}