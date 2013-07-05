<?php

namespace Mr\Api\Http\Adapter;

class ReadOnlyAdapter extends BaseAdapter
{
	/**
    * {@inheritdoc }
    */
	public function getDisallowedMethods()
	{
		return array(
			AbstractClient::METHOD_POST, 
			AbstractClient::METHOD_PUT, 
			AbstractClient::METHOD_DELETE
		);
	}
}