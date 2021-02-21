<?php 

namespace App\Controllers;

use Config\Services;

class Home extends BaseController
{

	/**
	 * the model name
	 *
	 * @var string
	 */
	protected $modelName = 'App\Controllers\Models\PermissionsModel';

	/**
	 * Index method.
	 */
	public function index()
	{
		# Colors: primary, secondary, success, danger, warning, info, light, dark
		$alerts[] = [
			'html'=>'Alert with success color and <a href="#" class="alert-link">an example link</a>!',
			'success'];
		$breadcrumb[] = ['text'=>'Home',    'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Library', 'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Library', 'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Data',    'active'=>true,  'href'=>'#'];

		// Collect Data
		$data['alerts'] = $alerts;
		$data['breadcrumb'] = $breadcrumb;
		$data['model'] = $this->model;

		return view('DefaultPage', $data);

	}

	/**
	 * Index method.
	 */
	public function sidebar()
	{
		# Colors: primary, secondary, success, danger, warning, info, light, dark
		$alerts[] = [
			'html'=>'Alert with success color and <a href="#" class="alert-link">an example link</a>!',
			'success'];
		$breadcrumb[] = ['text'=>'Home',    'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Library', 'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Library', 'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Data',    'active'=>true,  'href'=>'#'];

		// Collect Data
		#$data['alerts'] = $alerts;
		$data['breadcrumb'] = $breadcrumb;
		$data['model'] = $this->model;

		return view('DefaultSidebarPage', $data);

	}

	/**
	 * Index method.
	 */
	public function table()
	{

		helper("form");

		$perPage = $this->request->getGet('perPage') ?? 10;
		$ts = str_replace("*", "%", $this->request->getGet('ts')) ?? '';

		$entities = $this->model->like('name',$ts)
								->orLike('description',$ts)
								->paginate($perPage);

		# Colors: primary, secondary, success, danger, warning, info, light, dark
		$alerts[] = [
			'html'=>'Alert with success color and <a href="#" class="alert-link">an example link</a>!',
			'success'];
		$breadcrumb[] = ['text'=>'Home',    'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Library', 'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Library', 'active'=>false, 'href'=>'#'];
		$breadcrumb[] = ['text'=>'Data',    'active'=>true,  'href'=>'#'];

		// Collect Data

		$options = [
			'breadcrumb' => $breadcrumb,
			'model' => $this->model,
			'config' => $this->config,
			'entities' => $entities,
			'pager' => $this->model->pager,
			'request' => $this->request,
		];

		return view('DefaultTablePage', $options);

	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->config = config("idoit");
		$this->model = model($this->modelName);
	}

}
