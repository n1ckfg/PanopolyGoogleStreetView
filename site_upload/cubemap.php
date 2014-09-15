<?php
	class Vector2
	{
		var $x;
		var $y;
		
		function Vector2($x,$y)
		{
			$this->x = $x;
			$this->y = $y;
		}
	};
	
	class Vector3
	{
		var $x;
		var $y;
		var $z;
		
		function Vector3($x,$y,$z)
		{
			$this->x = $x;
			$this->y = $y;
			$this->z = $z;
		}
	};
	
	class SoyCubemap
	{
		var $mRatio;	//	w/h vector2
		var $mTileSize;	//	w/h vector2
		var $mTileMap;	//	2D array of falseUDLRFB
		var $mFaceMap;	//	reverse of mTileMap to vector2
		
		function SoyCubemap($Width,$Height,$Layout)
		{
			$Layout = str_split( $Layout );
			
			//	calc ratio
			$this->mRatio = GetRatio( $Width, $Height, $SquareTileSize );
			if ( $this->mRatio === false )
				return;
			$this->mTileSize = new Vector2( $SquareTileSize, $SquareTileSize );
			
			//	see if layout fits
			$RatioTileCount = $this->mRatio->x * $this->mRatio->y;
			$LayoutTileCount = count( $Layout );
			if ( $RatioTileCount != $LayoutTileCount )
			{
				//	scale if posss
				$Remainder = $RatioTileCount % $LayoutTileCount;
				if ( $Remainder != 0 )
				{
					OnError("Layout doesn't fit in ratio");
					$this->mRatio = false;
					return;
				}
				//	ratio can only scale upwards...
				if ( $RatioTileCount > $LayoutTileCount )
				{
					OnError("Layout doesn't have enough tiles to fit in ratio");
					$this->mRatio = false;
					return;
				}
				$Scale = $LayoutTileCount / $RatioTileCount;
				$this->mRatio->x *= $Scale;
				$this->mRatio->y *= $Scale;
				$this->mTileSize->x /= $Scale;
				$this->mTileSize->y /= $Scale;
			}
			
			//	make up 2d map
			for ( $x=0;	$x<$this->GetWidth();	$x++ )
			{
				for ( $y=0;	$y<$this->GetHeight();	$y++ )
				{
					$i = ($y*$this->GetWidth()) + $x;
					$this->mTileMap[$x][$y] = $Layout[$i];
					$this->mFaceMap[$Layout[$i]] = new Vector2($x,$y);
				}
			}
		}
		
		function IsValid()		{	return $this->mRatio !== false;	}
		function GetWidth()		{	return $this->mRatio->x;	}
		function GetHeight()	{	return $this->mRatio->y;	}
		
		//	apply map to a different image
		function Resize($Width,$Height)
		{
			//	all we need to do is change tile size
			$this->mTileSize->x = $Width / $this->mRatio->x;
			$this->mTileSize->y = $Height / $this->mRatio->y;
		}
		
		function GetFaceOffset($Face)
		{
			if ( !array_key_exists( $Face, $this->mFaceMap ) )
				return new Vector2(0,0);
			
			return $this->mFaceMap[$Face];
		}

		function ReadPixel_LatLon($Coords,$Image)
		{
			/*
			 // debug
			 $x = $Coords->x + kPiF;
			 $x /= kPiF * 2.0;
			 $y = $Coords->y + kPiF;
			 $y /= kPiF * 2.0;
			 return GetRgb( $x*255, 0, $y*255 );
			 */
			
			// The largest coordinate component determines which face we’re looking at.
			//	coords = pixel (camera) normal
			$coords = VectorFromCoordsRad($Coords);
			$ax = abs($coords->x);
			$ay = abs($coords->y);
			$az = abs($coords->z);
			$faceOffset;
			$x;
			$y;	//	offset from center of face
			
			//assert(ax != 0.0f || ay != 0.0f || az != 0.0f);
			
			if ($ax > $ay && $ax > $az)
			{//	facing x
				$x = $coords->z / $ax;
				$y = -$coords->y / $ax;
				if (0 < $coords->x)
				{
					$x = -$x;
					$faceOffset = $this->GetFaceOffset('R');
				}
				else
				{
					$faceOffset = $this->GetFaceOffset('L');
				}
			}
			else if ($ay > $ax && $ay > $az)
			{//	facing y
				$x = $coords->x / $ay;
				$y = $coords->z / $ay;
				if (0 < $coords->y)
				{
					$faceOffset = $this->GetFaceOffset('D');
				}
				else
				{
					$y = -$y;
					$faceOffset = $this->GetFaceOffset('U');
				}
			}
			else
			{//	must be facing z
				$x = $coords->x / $az;
				$y = -$coords->y / $az;
				if (0 < $coords->z)
				{
					$faceOffset = $this->GetFaceOffset('F');
				}
				else
				{
					$x = -$x;
					$faceOffset = $this->GetFaceOffset('B');
				}
			}
			
			$FaceSize = $this->mTileSize;
			$HalfSize = new Vector2( $FaceSize->x/2.0, $FaceSize->y/2.0 );
			
			$x = ($x * $HalfSize->x) + $HalfSize->x;
			$y = ($y * $HalfSize->y) + $HalfSize->y;
			assert( $x >= 0 && $x <= $FaceSize->x );
			assert( $y >= 0 && $y <= $FaceSize->y );

			$x += $faceOffset->x * $FaceSize->x;
			$y += $faceOffset->y * $FaceSize->y;
	
			/*
			var_dump($faceOffset);	echo "<p>";
			var_dump($FaceSize);	echo "<p>";
			var_dump($x);	echo "<p>";
			var_dump($y);	echo "<p>";
			exit(0);
			*/
			return ReadPixel_Clamped($Image, $x, $y );
		}
	};
	
	function GetRatio($Width,$Height,&$TileSize)
	{
		if ( $Width <= 0 || $Height <= 0 )
			return false;
		
		//	square
		if ( $Width == $Height )
		{
			$TileSize = $Width;
			return new Vector2(1,1);
		}
		
		if ( $Width > $Height )
		{
			$Remainder = $Width % $Height;
			if ( $Remainder == 0 )
			{
				$TileSize = $Height;
				return new Vector2($Width / $Height,1);
			}
			$TileSize = $Remainder;
			return new Vector2($Width/$Remainder,$Height/$Remainder);
		}
		else
		{
			$Ratio = GetRatio( $Height, $Width, $TileSize );
			return new Vector2( $Ratio->y, $Ratio->x );
		}
	}
	
	/*
	//	test
	$w = $_GET['width'];
	$h = $_GET['height'];
	$layout = $_GET['layout'];
	$Cubemap = new SoyCubemap( $w, $h, $layout );
	
	if ( !$Cubemap->IsValid() )
		return OnError("Invalid layout/size");
	
	//var_dump( $Cubemap );
	//	debug
	for ( $y=0;	$y<$Cubemap->GetHeight();	$y++)
	{
		echo '<div>';
		for ( $x=0;	$x<$Cubemap->GetWidth();	$x++)
		{
			echo $Cubemap->mTileMap[$x][$y];
		}
		echo '</div>';
	}
	 */
?>