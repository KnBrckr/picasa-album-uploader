/* Fixup the hidden <input> tags Picasa detects to create the upload stream of files */

function chURL(psize){
	jQuery("input[type='hidden']").each(function()
	{
		this.name = this.name.replace(/size=.*/,"size="+psize);
	});
}
