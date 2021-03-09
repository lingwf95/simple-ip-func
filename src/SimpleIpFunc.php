<?php
namespace lingwf\simple;

class SimpleIpFunc
{
	public function subnetMask($length)
	{
		$str = '';
		for ($i=1; $i <= 32; $i++) {
			if ($i <= $length) {
				$str .= '1';
			}else{
				$str .= '0';
			}
			if ($i != 32 && $i % 8 == 0) {
				$str .= '.';
			}
		}
		$arr = explode('.', $str);
		for ($i=0; $i < 4; $i++) {
			$arr[$i] = bindec($arr[$i]);
		}

		return implode('.', $arr);
	}

	public function subnetMaskLength($mask)
	{
		$length = 0;
		$split_mask = explode('.',$mask);
		foreach($split_mask as $v)
		{
			$length+=8-log(256-$v,2);
		}
		return $length;
	}
	
	/**
	 * 根据网段获取计算所有IP
	 * @param string $segment 网段 '139.217.0.1/24'
	 * @return array IP列表 ['139.217.0.1','139.217.0.2'……]
	 */
	public function getIpListBySegment($segment,$usedIpArray = [])
	{
		$usedIpArray = empty($usedIpArray) ? [] : array_flip($this->checkUsedIpArray($usedIpArray));
		$segmentInfo = explode("/", $segment);//['139.217.0.1',24]
		$beginIpArray = explode(".", $segmentInfo[0]);//[139,217,0,1]
		$mask = intval($segmentInfo['1']);//24
		$endIp = array();
		foreach ($beginIpArray as $ipKey => $item) {
			$beginFlag = 8 * ($ipKey);//0   8   16  24
			$endFlag = 8 * ($ipKey + 1);//8   16  24  32
			$decbinItem = str_pad(decbin($item), 8, "0", STR_PAD_LEFT);
			$endIp[] = $mask >= $endFlag ? $item : ($mask > $beginFlag ? bindec(str_pad(substr($decbinItem, 0, $mask - $beginFlag), 8, "1", STR_PAD_RIGHT)) : ($ipKey <= 2 ? pow(2, 8) - 1 : pow(2, 8) - 1));
		}
		$ipArray = array();
		for ($beginIp[0] = $beginIpArray[0]; $beginIp[0] <= $endIp[0]; $beginIp[0]++) {
			for ($beginIp[1] = $beginIpArray[1]; $beginIp[1] <= $endIp[1]; $beginIp[1]++) {
				for ($beginIp[2] = $beginIpArray[2]; $beginIp[2] <= $endIp[2]; $beginIp[2]++) {
					for ($beginIp[3] = $beginIpArray[3]; $beginIp[3] <= $endIp[3]; $beginIp[3]++) {
						$ip = implode(".", $beginIp);
						if (isset($usedIpArray[$ip])) {
							continue;
						}
						$ipArray[] = implode(".", $beginIp);
					}
				}
			}
		}
		return $ipArray;
	}
	
	
	public function checkUsedIpArray($array = [])
	{
		$used = [];
		foreach($array as $key => $value){
			if(count(explode('/',$value))>1){
				$used += $this->getIpListBySegment($value);
			}else{
				$used[] = $value;
			}
		}
		return $used;
	}
	
	/**
	 * 在指定网段中分配子网段
	 * @param string $segment 指定网段
	 * @param int $ipNum 需要的IP数
	 * @param array $usedIpArray 不可用（已经使用）的IP，默认为空数组
	 * @return bool|string 成功则返回分配的网段
	 */
	function allocateSegment($segment, $ipNum, $usedIpArray = [])
	{
		$usedIpArray = empty($usedIpArray) ? [] : array_flip($this->checkUsedIpArray($usedIpArray));
		//计算需要多少个IP
		$i = 0;
		$ipCount = pow(2, $i);
		while ($ipCount < $ipNum) {
			$i++;
			$ipCount = pow(2, $i);
		}
		$newMask = 32 - $i;
		//大网段的开始和结束IP
		$segmentInfo = explode("/", $segment);//['139.217.0.1',24]
		$beginIpArray = explode(".", $segmentInfo[0]);//[139,217,0,1]
		$mask = intval($segmentInfo['1']);//24
		if ($newMask < $mask) {
			return false;
		}
		$endIp = array();
		$step = [];
		foreach ($beginIpArray as $ipKey => $item) {
			$beginFlag = 8 * ($ipKey);//0   8   16  24
			$endFlag = 8 * ($ipKey + 1);//8   16  24  32
			$step[$ipKey] = $newMask > $endFlag ? 1 : ($endFlag - $newMask < 8 ? pow(2, $endFlag - $newMask) : pow(2, 8));
			$decbinItem = str_pad(decbin($item), 8, "0", STR_PAD_LEFT);
			$endIp[] = $mask >= $endFlag ? $item : ($mask > $beginFlag ? bindec(str_pad(substr($decbinItem, 0, $mask - $beginFlag), 8, "1", STR_PAD_RIGHT)) : ($ipKey <= 2 ? pow(2, 8) - 1 : pow(2, 8) - 1));
		}
		//遍历生成网段
		for ($beginIp[0] = $beginIpArray[0]; $beginIp[0] <= $endIp[0]; $beginIp[0] += $step[0]) {
			for ($beginIp[1] = $beginIpArray[1]; $beginIp[1] <= $endIp[1]; $beginIp[1] += $step[1]) {
				for ($beginIp[2] = $beginIpArray[2]; $beginIp[2] <= $endIp[2]; $beginIp[2] += $step[2]) {
					for ($beginIp[3] = $beginIpArray[3]; $beginIp[3] <= $endIp[3]; $beginIp[3] += $step[3]) {
						$newSegment = implode('.', $beginIp) . '/' . $newMask;
						//获取该网段所有的IP
						$ipArray = $this->getIpListBySegment($newSegment);
						$canUse = true;
						//判断该网段是否可用
						if (!empty($usedIpArray)) {
							foreach ($ipArray as $ip) {
								if (isset($usedIpArray[$ip])) {
									$canUse = false;
									break;
								}
							}
						}
						if ($canUse) {
							return $newSegment;
						}
					}
				}
			}
		}
		return false;
	}
	
	public function ip_in_network($ip, $network)
	{
		$ip = (double) (sprintf("%u", ip2long($ip)));
		$s = explode('/', $network);
		$network_start = (double) (sprintf("%u", ip2long($s[0])));
		$network_len = pow(2, 32 - $s[1]);
		$network_end = $network_start + $network_len - 1;
	 
		if ($ip >= $network_start && $ip <= $network_end)
		{
			return true;
		}
		return false;
	}
}
