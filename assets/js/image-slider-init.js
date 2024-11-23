(function( root, $, undefined ) {
  "use strict";



  $(function () {
    const windowWidth = $(window).width();
    // if(windowWidth < 576) {
    //
    // } else if(windowWidth < 992) {
    //
    // } else if(windowWidth < 1200) {
    //
    // } else {
    //
    // }

    $('.gallery-images .gallery-slideshow').each(function(i, e) {
      var galleryID = $(e).attr('data-blockID');
      $(e).slick({
        infinite: false,
        slidesToShow: 1,
        slidesToScroll: 1,
        dots: false,
        centerMode: false,
        // variableWidth: true,
        nextArrow: $('#' + galleryID + ' .gallery-controles .next'),
        prevArrow: $('#' + galleryID + ' .gallery-controles .prev'),
        // appendDots: $('#' + sliderID + ' .mapi-slide-dots'),
      });
    });




  });


} ( this, jQuery ));
