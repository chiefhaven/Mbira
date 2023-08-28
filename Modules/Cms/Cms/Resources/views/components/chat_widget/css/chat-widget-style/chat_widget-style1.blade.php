<style type="text/css">
  /* Click To chatWidget Main Container */
  .cp-style1{
    	display: inline-block;
    	position: fixed;
    	letter-spacing: 0.1px;
    	font-family: 'Open Sans', "Helvetica Neue", Helvetica, Arial, sans-serif;
    	z-index: 9999;
  }
  .cp-right-bottom{
    	bottom: 1em;
    	right: 1em;
    	float: right;
  }

  /* Floating Panel Container */
  .cp-style1 .cp-panel{
    	display: none;
      padding: 15px 10px;
    	margin-bottom: 5%;
    	width: 280px;
    	border-radius: 10px;
    	box-shadow: 0px 3px 7px rgba(12, 12, 12, 0.18);
  }
  .cp-style1 .cp-panel .cp-list{
      display: inline-flex;
      padding: 7px 5px;
      width: 100%;
      cursor: pointer;
  }
  .cp-style1 .cp-panel .cp-list:hover{
      border-radius: 10px;
      box-shadow: 0px 0px 5px 0px #0000002e;
  }
  .cp-style1 .cp-list .cp-image{    
      display: flex;
      width: 60px;
      text-align: center;
      justify-content: center;
  }
  .cp-style1 .cp-image img{
      margin-top: 3px;
      width: 27px;
      height: 27px;
  }
  .cp-style1 .cp-image img,
  .cp-style1 .cp-panel .cp-list:hover img{
      -webkit-transition: all .3s ease-in-out;
      -moz-transition: all .3s ease-in-out;
      -ms-transition: all .3s ease-in-out;
      -o-transition: all .3s ease-in-out;
      transition: all .3s ease-in-out;
  }
  .cp-style1 .cp-panel .cp-content{
      padding-left: 0;
  }
  .cp-style1 .cp-content h2{
      padding-top: 0; 
  	  margin-bottom: 0;
  	  font-size: 15px;
  	  font-weight: 700;
  	  line-height: 18px;
  }
  .cp-style1 .cp-content p{
  	  margin-bottom: 0;
  	  font-size: 12px;
  }

  /* Right Bottom Floating Button */
  .cp-style1 .cp-button{
  	  padding: 12px;
  	  width: 50px;
      height: 50px;
  	  border-radius: 100%;
  	  border: 1px solid #fff;
      box-shadow: 0 0 10px rgba(12, 12, 12, 0.3);    
      cursor: pointer;
  }
  .cp-style1 .cp-button:hover{
  	  box-shadow: 0px 0px 10px rgba(12, 12, 12, 0.5);
  	  -webkit-transition: all .3s ease-in-out;
  	  -moz-transition: all .3s ease-in-out;
  	  -ms-transition: all .3s ease-in-out;
  	  -o-transition: all .3s ease-in-out;
  	  transition: all .3s ease-in-out;
  }
  .cp-style1 .cp-button .fa-comments-o{
    	font-size: 30px;
  }

  /* Floating Button Forward Rotation */
  .rotateForward{
      animation-name: rotateF;
      animation-duration: 0.65s;
      animation-iteration-count: 1;
  }

  @keyframes rotateF{
    	from{
          transform: rotate(0deg);
          -o-transform: rotate(0deg);
          -ms-transform: rotate(0deg);
          -moz-transform: rotate(0deg);
          -webkit-transform: rotate(0deg);
    	}
    	to{
          transform: rotate(360deg);
          -o-transform: rotate(360deg);
          -ms-transform: rotate(360deg);
          -moz-transform: rotate(360deg);
          -webkit-transform: rotate(360deg);
    	}
  }

  /* Floating Button Backward Rotation */
  .rotateBackward{
      animation-name: rotateB;
      animation-duration: 0.65s;
      animation-iteration-count: 1;
  }

  @keyframes rotateB{
    	from{
          transform: rotate(360deg);
          -o-transform: rotate(360deg);
          -ms-transform: rotate(360deg);
          -moz-transform: rotate(360deg);
          -webkit-transform: rotate(360deg);
    	}
    	to{
          transform: rotate(0deg);
          -o-transform: rotate(0deg);
          -ms-transform: rotate(0deg);
          -moz-transform: rotate(0deg);
          -webkit-transform: rotate(0deg);
    	}
  }

  /* Media Css for Smaller Device */
  @media(max-width: 576px){

  	  .cp-style1 .cp-panel{
  	      width: 290px;
  	  }
  }

  /* Medium devices (landscape tablets, 0px and 1280px) */
  @media all and (max-width: 1280px) and (min-width: 0px){
      
      .cp-right-bottom {
          bottom: 10px;
          right: 10px;
      }
      .cp-style1 .cp-panel {
          margin-bottom: 5px;
      }
      .cp-style1 .cp-panel {
          padding: 10px;
      }
      .cp-style1 .cp-panel .cp-list {
          padding: 5px 0;
      }
  }
</style>