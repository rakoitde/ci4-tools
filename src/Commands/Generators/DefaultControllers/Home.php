<?php 

namespace App\Controllers;

use Config\Services;

class DefaultController extends BaseController
{

	/**
	 * the model name
	 *
	 * @var string
	 */
	protected $modelName = 'App\Controllers\Models\PermissionsModel';

	/**
	 * holds breadcrumb
	 *
	 * @var array
	 */
	protected $breadcrumb = [];

	/**
	 * holds alerts
	 *
	 * [ 'html' => 'Message with <a href="#" class="alert-link">link</a>!', 'color' => 'success'];
	 # Colors: primary, secondary, success, danger, warning, info, light, dark
	 *
	 * @var array
	 */
	protected $alerts = [];

	/**
	 * Helper
	 *
	 * @var array
	 */
	protected $helpers = ['auth','form'];

	/**
	 * Index method.
	 */
	public function index()
	{

		$this->alerts[] = [
			'html' => 'Alert with success color and <a href="#" class="alert-link">an example link</a>!',
			'color' => 'success'];
		$this->breadcrumb[] = ['text'=>'Index', 'active'=>true, 'href'=>'#'];

		// Collect Data
		$data = [
			'breadcrumb' => $this->breadcrumb,
			'alerts'     => $this->alerts,
			'model'      => $this->model,
		];

		return view('DefaultPage', $data);

	}

	/**
	 * Index method.
	 */
	public function sidebar()
	{

		$this->alerts[] = [
			'html' => 'Alert with success color and <a href="#" class="alert-link">an example link</a>!',
			'color' => 'success'];
		$this->breadcrumb[] = ['text'=>'Sidebar', 'active'=>true, 'href'=>'#'];

		// Collect Data
		$data = [
			'breadcrumb' => $this->breadcrumb,
			'alerts'     => $this->alerts,
			'model'      => $this->model,
		];

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

		$this->alerts[] = [
			'html' => 'Alert with success color and <a href="#" class="alert-link">an example link</a>!',
			'color' => 'success'];
		$this->breadcrumb[] = ['text'=>'Sidebar', 'active'=>true, 'href'=>'#'];

		// Collect Data
		$data = [
			'breadcrumb' => $this->breadcrumb,
			'alerts'     => $this->alerts,
			'model'      => $this->model,
			'config'     => $this->config,
			'entities'   => $entities,
			'pager'      => $this->model->pager,
			'request'    => $this->request,
		];

		return view('DefaultTablePage', $data);

	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->config = config("idoit");
		$this->model = model($this->modelName);

		$this->breadcrumb[] = ['text'=>'Start', 'active'=>false, 'href'=>'#'];
		$this->breadcrumb[] = ['text'=>'Index', 'active'=>false, 'href'=>'#'];
	}

}
