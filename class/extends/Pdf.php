<?php

    namespace Wonder;

    use Fpdf\Fpdf;

    class Pdf extends Fpdf {

        private $FONT = "";
        private $FONT_BOLD = "";

        function LoadFont($regular = 'helvetica', $bold = null) {

            global $ROOT_APP;

            if (!in_array($regular, [ 'helvetica', 'courier', 'symbol', 'times' ])) {

                $path = explode('-', $regular);
                $dir1 = $path[0];
                $dir2 = $path[1];
                
                $this->AddFont($regular, "", $regular.'.php', $ROOT_APP.'/assets/font/'.$dir1.'/'.$dir2);

            }

            $bold = ($bold == null || empty($bold)) ? $regular : $bold;

            if (!in_array($bold, [ 'helvetica', 'courier', 'symbol', 'times' ])) {

                $path = explode('-', $bold);
                $dir1 = $path[0];
                $dir2 = $path[1];

                $this->AddFont($bold, "B", $bold.'.php', $ROOT_APP.'/assets/font/'.$dir1.'/'.$dir2);

            }

            $this->FONT = $regular;
            $this->FONT_BOLD = $bold;

            $this->Font('12');

        }
        function Font($size) { $this->SetFont($this->FONT, '', $size); }
        function FontBold($size) { $this->SetFont($this->FONT_BOLD, 'B', $size); }

        function StartTransform(){
            //save the current graphic state
            $this->_out('q');
        }

        function ScaleX($s_x, $x='', $y=''){
            $this->Scale($s_x, 100, $x, $y);
        }
        function ScaleY($s_y, $x='', $y=''){
            $this->Scale(100, $s_y, $x, $y);
        }
        function ScaleXY($s, $x='', $y=''){
            $this->Scale($s, $s, $x, $y);
        }
        function Scale($s_x, $s_y, $x='', $y=''){
            if($x === '')
                $x=$this->x;
            if($y === '')
                $y=$this->y;
            if($s_x == 0 || $s_y == 0)
                $this->Error('Please use values unequal to zero for Scaling');
            $y=($this->h-$y)*$this->k;
            $x*=$this->k;
            //calculate elements of transformation matrix
            $s_x/=100;
            $s_y/=100;
            $tm[0]=$s_x;
            $tm[1]=0;
            $tm[2]=0;
            $tm[3]=$s_y;
            $tm[4]=$x*(1-$s_x);
            $tm[5]=$y*(1-$s_y);
            //scale the coordinate system
            $this->Transform($tm);
        }

        function MirrorH($x=''){
            $this->Scale(-100, 100, $x);
        }
        function MirrorV($y=''){
            $this->Scale(100, -100, '', $y);
        }
        function MirrorP($x='',$y=''){
            $this->Scale(-100, -100, $x, $y);
        }
        function MirrorL($angle=0, $x='',$y=''){
            $this->Scale(-100, 100, $x, $y);
            $this->Rotate(-2*($angle-90),$x,$y);
        }

        function TranslateX($t_x){
            $this->Translate($t_x, 0);
        }
        function TranslateY($t_y){
            $this->Translate(0, $t_y);
        }
        function Translate($t_x, $t_y){
            //calculate elements of transformation matrix
            $tm[0]=1;
            $tm[1]=0;
            $tm[2]=0;
            $tm[3]=1;
            $tm[4]=$t_x*$this->k;
            $tm[5]=-$t_y*$this->k;
            //translate the coordinate system
            $this->Transform($tm);
        }
        function Rotate($angle, $x='', $y=''){
            if($x === '')
                $x=$this->x;
            if($y === '')
                $y=$this->y;
            $y=($this->h-$y)*$this->k;
            $x*=$this->k;
            //calculate elements of transformation matrix
            $tm[0]=cos(deg2rad($angle));
            $tm[1]=sin(deg2rad($angle));
            $tm[2]=-$tm[1];
            $tm[3]=$tm[0];
            $tm[4]=$x+$tm[1]*$y-$tm[0]*$x;
            $tm[5]=$y-$tm[0]*$y-$tm[1]*$x;
            //rotate the coordinate system around ($x,$y)
            $this->Transform($tm);
        }

        function SkewX($angle_x, $x='', $y=''){
            $this->Skew($angle_x, 0, $x, $y);
        }
        function SkewY($angle_y, $x='', $y=''){
            $this->Skew(0, $angle_y, $x, $y);
        }
        function Skew($angle_x, $angle_y, $x='', $y=''){
            if($x === '')
                $x=$this->x;
            if($y === '')
                $y=$this->y;
            if($angle_x <= -90 || $angle_x >= 90 || $angle_y <= -90 || $angle_y >= 90)
                $this->Error('Please use values between -90° and 90° for skewing');
            $x*=$this->k;
            $y=($this->h-$y)*$this->k;
            //calculate elements of transformation matrix
            $tm[0]=1;
            $tm[1]=tan(deg2rad($angle_y));
            $tm[2]=tan(deg2rad($angle_x));
            $tm[3]=1;
            $tm[4]=-$tm[2]*$y;
            $tm[5]=-$tm[1]*$x;
            //skew the coordinate system
            $this->Transform($tm);
        }

        function Transform($tm){
            $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F cm', $tm[0],$tm[1],$tm[2],$tm[3],$tm[4],$tm[5]));
        }

        function StopTransform(){
            //restore previous graphic state
            $this->_out('Q');
        }

    }

?>