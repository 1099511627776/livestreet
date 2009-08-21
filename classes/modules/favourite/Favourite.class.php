<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__));
require_once('mapper/Favourite.mapper.class.php');

/**
 * Модуль для работы с голосованиями
 *
 */
class LsFavourite extends Module {		
	protected $oMapper;	
		
	/**
	 * Инициализация
	 *
	 */
	public function Init() {		
		$this->oMapper=new Mapper_Favourite($this->Database_GetConnect());
	}
	
	/**
	 * Получает информацию о том, найден ли таргет в избранном или нет
	 *
	 * @param  string $sTargetId
	 * @param  string $sTargetType
	 * @param  string $sUserId
	 * @return FavouriteEntity_Favourite|null
	 */
	public function GetFavourite($sTargetId,$sTargetType,$sUserId) {
		$data=$this->GetFavouritesByArray($sTargetId,$sTargetType,$sUserId);
		return (isset($data[$sTargetId]))
			? $data[$sTargetId]
			: null;
	}
	
	/**
	 * Получить список избранного по списку айдишников
	 *
	 * @param  array  $aTargetId
	 * @param  string $sTargetType
	 * @param  string $sUserId
	 * @return array
	 */
	public function GetFavouritesByArray($aTargetId,$sTargetType,$sUserId) {
		if (!$aTargetId) {
			return array();
		}
		if (1) {
			return $this->GetFavouritesByArraySolid($aTargetId,$sTargetType,$sUserId);
		}
		if (!is_array($aTargetId)) {
			$aTargetId=array($aTargetId);
		}
		$aTargetId=array_unique($aTargetId);
		$aFavourite=array();
		$aIdNotNeedQuery=array();
		/**
		 * Делаем мульти-запрос к кешу
		 */
		$aCacheKeys=func_build_cache_keys($aTargetId,"favourite_{$sTargetType}_",'_'.$sUserId);
		if (false !== ($data = $this->Cache_Get($aCacheKeys))) {			
			/**
			 * проверяем что досталось из кеша
			 */
			foreach ($aCacheKeys as $sValue => $sKey ) {
				if (array_key_exists($sKey,$data)) {	
					if ($data[$sKey]) {
						$aFavourite[$data[$sKey]->getTargetId()]=$data[$sKey];
					} else {
						$aIdNotNeedQuery[]=$sValue;
					}
				} 
			}
		}
		/**
		 * Смотрим каких топиков не было в кеше и делаем запрос в БД
		 */		
		$aIdNeedQuery=array_diff($aTargetId,array_keys($aFavourite));		
		$aIdNeedQuery=array_diff($aIdNeedQuery,$aIdNotNeedQuery);		
		$aIdNeedStore=$aIdNeedQuery;
		if ($data = $this->oMapper->GetFavouriteByArray($aIdNeedQuery,$sTargetType,$sUserId)) {
			foreach ($data as $oFavourite) {
				/**
				 * Добавляем к результату и сохраняем в кеш
				 */
				$aFavourite[$oFavourite->getTargetId()]=$oFavourite;
				$this->Cache_Set($oFavourite, "favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$oFavourite->getFavouriterId()}", array(), 60*60*24*7);
				$aIdNeedStore=array_diff($aIdNeedStore,array($oFavourite->getTargetId()));
			}
		}
		/**
		 * Сохраняем в кеш запросы не вернувшие результата
		 */
		foreach ($aIdNeedStore as $sId) {
			$this->Cache_Set(null, "favourite_{$sTargetType}_{$sId}_{$sUserId}", array(), 60*60*24*7);
		}		
		/**
		 * Сортируем результат согласно входящему массиву
		 */
		$aFavourite=func_array_sort_by_keys($aFavourite,$aTargetId);
		return $aFavourite;		
	}
	/**
	 * Получить список голосований по списку айдишников, но используя единый кеш
	 *
	 * @param  array  $aTargetId
	 * @param  string $sTargetType
	 * @param  string $sUserId
	 * @return array
	 */
	public function GetFavouritesByArraySolid($aTargetId,$sTargetType,$sUserId) {
		if (!is_array($aTargetId)) {
			$aTargetId=array($aTargetId);
		}
		$aTargetId=array_unique($aTargetId);	
		$aFavourites=array();	
		$s=join(',',$aTargetId);
		if (false === ($data = $this->Cache_Get("favourite_{$sTargetType}_{$sUserId}_id_{$s}"))) {			
			$data = $this->oMapper->GetFavouritesByArray($aTargetId,$sTargetType,$sUserId);
			foreach ($data as $oFavourite) {
				$aFavourites[$oFavourite->getTargetId()]=$oFavourite;
			}
			$this->Cache_Set($aFavourites, "favourite_{$sTargetType}_{$sUserId}_id_{$s}", array("favourite_update_{$sTargetType}_{$sUserId}"), 60*60*24*1);
			return $aFavourites;
		}		
		return $data;
	}
	
	/**
	 * Получает список таргеов из избранного
	 *
	 * @param  string $sUserId
	 * @param  string $sTargetType
	 * @param  int $iCount
	 * @param  int $iCurrPage
	 * @param  int $iPerPage
	 * @return array
	 */
	public function GetFavouritesByUserId($sUserId,$sTargetType,$iCurrPage,$iPerPage) {		
		if (false === ($data = $this->Cache_Get("{$sTargetType}_favourite_user_{$sUserId}_{$iCurrPage}_{$iPerPage}"))) {			
			$data = array(
				'collection' => $this->oMapper->GetFavouritesByUserId($sUserId,$sTargetType,$iCount,$iCurrPage,$iPerPage),
				'count'      => $iCount
			);
			$this->Cache_Set(
				$data, 
				"{$sTargetType}_favourite_user_{$sUserId}_{$iCurrPage}_{$iPerPage}", 
				array(
					"favourite_{$sTargetType}_change",
					"favourite_{$sTargetType}_change_user_{$sUserId}"
				), 
				60*60*24*1
			);
		}
		/// $data['collection']=$this->GetTopicsAdditionalData($data['collection']);		
		return $data;		
	}
	/**
	 * Возвращает число таргетов определенного типа в избранном по ID пользователя
	 *
	 * @param  string $sUserId
	 * @param  string $sTargetType
	 * @return array
	 */
	public function GetCountFavouritesByUserId($sUserId,$sTargetType) {
		if (false === ($data = $this->Cache_Get("{$sTargetType}_count_favourite_user_{$sUserId}"))) {			
			$data = $this->oMapper->GetCountFavouritesByUserId($sUserId,$sTargetType);
			$this->Cache_Set(
				$data, 
				"{$sTargetType}_count_favourite_user_{$sUserId}", 
				array(
					"favourite_{$sTargetType}_change",
					"favourite_{$sTargetType}_change_user_{$sUserId}"
				), 
				60*60*24*1
			);
		}
		return $data;	
	}
	
	/**
	 * Добавляет таргет в избранное
	 *
	 * @param  FavouriteEntity_Favourite $oFavourite
	 * @return bool
	 */
	public function AddFavourite(FavouriteEntity_Favourite $oFavourite) {
		//чистим зависимые кеши
		$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array("favourite_{$oFavourite->getTargetType()}_change_user_{$oFavourite->getUserId()}"));						
		$this->Cache_Delete("favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$oFavourite->getUserId()}");						
		return $this->oMapper->AddFavourite($oFavourite);
	}
	/**
	 * Удаляет таргет из избранного
	 *
	 * @param  FavouriteEntity_Favourite $oFavourite
	 * @return bool
	 */
	public function DeleteFavourite(FavouriteEntity_Favourite $oFavourite) {
		//чистим зависимые кеши
		$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array("favourite_{$oFavourite->getTargetType()}_change_user_{$oFavourite->getUserId()}"));
		$this->Cache_Delete("favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$oFavourite->getUserId()}");
		return $this->oMapper->DeleteFavourite($oFavourite);
	}
	/**
	 * Меняет параметры публикации у таргета
	 *
	 * @param  string $sTargetId
	 * @param  string $sTargetType 
	 * @param  string $iPublish
	 * @return bool
	 */
	public function SetFavouriteTargetPublish($sTargetId,$sTargetType,$iPublish) {
		$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array("favourite_{$sTargetType}_change"));
		return $this->oMapperTopic->SetFavouriteTargetPublish($sTopicId,$sTargetType,$iPublish);
	}	
}
?>