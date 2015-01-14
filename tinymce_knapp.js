(function() {
   tinymce.create('tinymce.plugins.infoboks', {
      init : function(ed, url) {
         ed.addButton('bibvenn_infoboks', {
            title : 'Bibvenn infoboks',
            image : url+'/bibvenn_infoboks.png',
            onclick : function() {
               var id = prompt("ISBN\n(f.eks. 87-87531-60-7)\n\n... eller URL\n(f.eks. http://urn.nb.no/URN:NBN:no-nb_digibok_2011060106106)", "");
               		   
               if (id != null && id != '') {
				ed.execCommand('mceInsertContent', false, '[bibvenn_infoboks id="'+id+'"]');
               }
            }
         });
      },
      createControl : function(n, cm) {
         return null;
      },
      getInfo : function() {
         return {
            longname : "Bibvenn infoboks",
            author : 'Håkon M. E. Sundaune',
            authorurl : 'http://www.bibvenn.no',
            infourl : 'http://www.sundaune.no',
            version : "1.0"
         };
      }
   });
   tinymce.PluginManager.add('bibvenn_infoboks', tinymce.plugins.infoboks);
})();
