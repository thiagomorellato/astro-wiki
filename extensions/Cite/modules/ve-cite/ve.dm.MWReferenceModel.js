'use strict';

/*!
 * VisualEditor DataModel MWReferenceModel class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Corresponds to one ref and its metadata, chosen for an action.
 *
 * TODO: Distinguish this module from ve.dm.MWReferenceNode
 *
 * @constructor
 * @mixes OO.EventEmitter
 * @param {ve.dm.Document} [parentDoc] The parent Document we can use to auto-generate a blank
 *  Document for the reference in case {@see setDocument} was never called
 * @property {ve.dm.Document|Function|undefined} doc Might be deferred via a function, to be
 *  lazy-evaluated when {@see getDocument} is called
 */
ve.dm.MWReferenceModel = function VeDmMWReferenceModel( parentDoc ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	// Properties
	this.extendsRef = null;
	this.listKey = '';
	this.listGroup = '';
	this.listIndex = null;
	this.group = '';
	if ( parentDoc ) {
		this.doc = () => parentDoc.cloneWithData( [
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
	}
};

/* Inheritance */

OO.mixinClass( ve.dm.MWReferenceModel, OO.EventEmitter );

/* Static Methods */

/**
 * Create a reference model from a reference internal item.
 *
 * @param {ve.dm.MWReferenceNode} node Reference node
 * @return {ve.dm.MWReferenceModel} Reference model
 */
ve.dm.MWReferenceModel.static.newFromReferenceNode = function ( node ) {
	const doc = node.getDocument();
	const internalList = doc.getInternalList();
	const attributes = node.getAttributes();
	const ref = new ve.dm.MWReferenceModel();

	ref.extendsRef = attributes.extendsRef;
	ref.listKey = attributes.listKey;
	ref.listGroup = attributes.listGroup;
	ref.listIndex = attributes.listIndex;
	ref.group = attributes.refGroup;
	ref.doc = function () {
		// cloneFromRange is very expensive, so lazy evaluate it
		return doc.cloneFromRange( internalList.getItemNode( attributes.listIndex ).getRange() );
	};

	return ref;
};

/* Methods */

/**
 * Find matching item in a surface.
 *
 * @param {ve.dm.Surface} surfaceModel Surface reference is in
 * @return {ve.dm.InternalItemNode|null} Internal reference item, null if none exists
 */
ve.dm.MWReferenceModel.prototype.findInternalItem = function ( surfaceModel ) {
	if ( this.listIndex !== null ) {
		return surfaceModel.getDocument().getInternalList().getItemNode( this.listIndex );
	}
	return null;
};

/**
 * Insert reference internal item into a surface.
 *
 * If the internal item for this reference doesn't exist, use this method to create one.
 * The inserted reference is empty and auto-numbered.
 *
 * @param {ve.dm.Surface} surfaceModel Surface model of main document
 */
ve.dm.MWReferenceModel.prototype.insertInternalItem = function ( surfaceModel ) {
	// Create new internal item
	const doc = surfaceModel.getDocument();
	const internalList = doc.getInternalList();

	// Fill in data
	this.listKey = 'auto/' + internalList.getNextUniqueNumber();
	this.listGroup = 'mwReference/' + this.group;

	// Insert internal reference item into document
	const item = internalList.getItemInsertion( this.listGroup, this.listKey, [] );
	surfaceModel.change( item.transaction );
	this.listIndex = item.index;

	// Inject reference document into internal reference item
	surfaceModel.change(
		ve.dm.TransactionBuilder.static.newFromDocumentInsertion(
			doc,
			internalList.getItemNode( item.index ).getRange().start,
			this.getDocument()
		)
	);
};

/**
 * Update an internal reference item.
 *
 * An internal item for the reference will be created if no `ref` argument is given.
 *
 * @param {ve.dm.Surface} surfaceModel Surface model of main document
 */
ve.dm.MWReferenceModel.prototype.updateInternalItem = function ( surfaceModel ) {
	const doc = surfaceModel.getDocument();
	const internalList = doc.getInternalList();
	const listGroup = 'mwReference/' + this.group;

	// Group/key has changed
	if ( this.listGroup !== listGroup ) {
		// Get all reference nodes with the same group and key
		const group = internalList.getNodeGroup( this.listGroup );
		const refNodes = group.keyedNodes[ this.listKey ] ?
			group.keyedNodes[ this.listKey ].slice() :
			[ group.firstNodes[ this.listIndex ] ];
		// Check for name collision when moving items between groups
		const keyIndex = internalList.getKeyIndex( this.listGroup, this.listKey );
		if ( keyIndex !== undefined ) {
			// Resolve name collision by generating a new list key
			this.listKey = 'auto/' + internalList.getNextUniqueNumber();
		}
		// Update the group name of all references nodes with the same group and key
		const txs = [];
		for ( let i = 0, len = refNodes.length; i < len; i++ ) {
			txs.push( ve.dm.TransactionBuilder.static.newFromAttributeChanges(
				doc,
				refNodes[ i ].getOuterRange().start,
				{ refGroup: this.group, listGroup: listGroup }
			) );
		}
		surfaceModel.change( txs );
		this.listGroup = listGroup;
	}
	// Update internal node content
	const itemNodeRange = internalList.getItemNode( this.listIndex ).getRange();
	surfaceModel.change(
		ve.dm.TransactionBuilder.static
			.newFromRemoval( doc, itemNodeRange, true ) );
	surfaceModel.change(
		ve.dm.TransactionBuilder.static
			.newFromDocumentInsertion( doc, itemNodeRange.start, this.getDocument() ) );
};

/**
 * Insert a reference node at the end of a surface fragment. Will also add an internal item
 * if needed.
 *
 * @param {ve.dm.SurfaceFragment} surfaceFragment Surface fragment to insert at
 */
ve.dm.MWReferenceModel.prototype.insertIntoFragment = function ( surfaceFragment ) {
	const surfaceModel = surfaceFragment.getSurface();

	if ( !this.findInternalItem( surfaceModel ) ) {
		this.insertInternalItem( surfaceModel );
	}
	this.insertReferenceNode( surfaceFragment );
};

/**
 * Insert a reference node at the end of a surface fragment.
 *
 * @param {ve.dm.SurfaceFragment} surfaceFragment Surface fragment to insert at
 * @param {boolean} [placeholder] Reference is a placeholder for staging purposes
 */
ve.dm.MWReferenceModel.prototype.insertReferenceNode = function ( surfaceFragment, placeholder ) {
	const attributes = {
		extendsRef: this.extendsRef,
		listKey: this.listKey,
		listGroup: this.listGroup,
		listIndex: this.listIndex,
		refGroup: this.group
	};
	if ( placeholder ) {
		attributes.placeholder = true;
	}
	surfaceFragment
		.insertContent( [
			{
				type: 'mwReference',
				attributes: attributes,
				// See ve.dm.MWReferenceNode.static.cloneElement
				originalDomElementsHash: Math.random()
			},
			{ type: '/mwReference' }
		] );
};

/**
 * Get the key of a reference in the references list.
 *
 * @return {string} Reference's list key
 */
ve.dm.MWReferenceModel.prototype.getListKey = function () {
	return this.listKey;
};

/**
 * Get the name of the group a references list is in.
 *
 * @return {string} References list's group
 */
ve.dm.MWReferenceModel.prototype.getListGroup = function () {
	return this.listGroup;
};

/**
 * Get the index of reference in the references list.
 *
 * @return {string} Reference's index
 */
ve.dm.MWReferenceModel.prototype.getListIndex = function () {
	return this.listIndex;
};

/**
 * Get the name of the group a reference is in.
 *
 * @return {string} Reference's group
 */
ve.dm.MWReferenceModel.prototype.getGroup = function () {
	return this.group;
};

/**
 * Get reference document.
 *
 * Auto-generates a blank document if no document exists.
 *
 * @return {ve.dm.Document} The (small) document with the content of the reference
 */
ve.dm.MWReferenceModel.prototype.getDocument = function () {
	if ( typeof this.doc === 'function' ) {
		this.doc = this.doc();
	}
	return this.doc;
};

/**
 * Set the name of the group a reference is in.
 *
 * @param {string} group Reference's group
 */
ve.dm.MWReferenceModel.prototype.setGroup = function ( group ) {
	this.group = group;
};

/**
 * Set the reference document.
 *
 * @param {ve.dm.Document} doc The (small) document with the content of the reference
 */
ve.dm.MWReferenceModel.prototype.setDocument = function ( doc ) {
	this.doc = doc;
};
